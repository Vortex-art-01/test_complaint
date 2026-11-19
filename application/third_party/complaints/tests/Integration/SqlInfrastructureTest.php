<?php

declare(strict_types=1);

namespace Complaints\Tests\Integration;

use Complaints\Application\Access\Actor;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\BuyComplaintCycle;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\Document\AttachmentOwner;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Shared\Period;
use Complaints\Infrastructure\Persistence\BuyComplaintMapper;
use Complaints\Infrastructure\Persistence\Database;
use Complaints\Infrastructure\Persistence\DocsTables;
use Complaints\Infrastructure\Persistence\PdoTransactionRunner;
use Complaints\Infrastructure\Persistence\ProjectSchema;
use Complaints\Infrastructure\Persistence\ReferenceIds;
use Complaints\Infrastructure\Persistence\SqlAttachmentRepository;
use Complaints\Infrastructure\Persistence\SqlBuyComplaintRepository;
use Complaints\Infrastructure\Persistence\SqlDocumentRepository;
use Complaints\Infrastructure\Persistence\SqlValues;
use Complaints\Infrastructure\System\StatusApplicationEligibility;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDOException;
use ReflectionClass;
use RuntimeException;

final class SqlInfrastructureTest extends SqlTestCase
{
    private const REFERENCE_TABLES = [
        'DECISION_' => 'ref_trd_complaint_st',
        'METHOD_' => 'ref_complaint_review_method',
        'ROLE_' => 'ref_trd_complaint_role',
        'LOT_' => 'ref_trd_complaint_lots_decision',
        'DATE_' => 'ref_trd_complaint_date_type',
        'TRD_DOC_' => 'ref_trd_complaint_docs_type',
        'COMPLAINT_' => 'ref_app_complaint_st',
        'APP_DOC_' => 'ref_app_complaint_docs_type',
        'VERDICT_' => 'ref_app_complaint_lots_verdict',
    ];

    private function values(): SqlValues
    {
        return new SqlValues(new DateTimeZone('Asia/Almaty'));
    }

    private function actor(int $userId, int $organizationId): Actor
    {
        return new Actor($userId, $organizationId);
    }

    private function decisions(): SqlBuyComplaintRepository
    {
        return new SqlBuyComplaintRepository(new Database($this->pdo), new BuyComplaintMapper($this->values()), $this->values());
    }

    private function decision(): int
    {
        $timeZone = new DateTimeZone('Asia/Almaty');

        return $this->decisions()->create(new BuyComplaintCycle(
            1,
            500,
            Period::ofDays('2026-10-15', '2026-10-19', $timeZone),
            Period::ofDays('2026-10-20', '2026-10-22', $timeZone),
        ), new DateTimeImmutable('2026-10-20 00:10:00+05:00')) ?? self::fail('Решение не создано.');
    }

    public function testMomentsKeepTheInstantAndComeBackInModuleTimeZone(): void
    {
        $db = new Database($this->pdo);
        $moment = new DateTimeImmutable('2026-10-14 18:00:00+05:00');

        $stored = $db->value('SELECT CAST(:at AS timestamptz)', ['at' => $moment]);
        $restored = $this->values()->moment($stored);

        self::assertSame('2026-10-14 13:00:00+00', $stored, 'Сессия в UTC.');
        self::assertSame('2026-10-14 18:00:00+05:00', $restored->format('Y-m-d H:i:sP'));
        self::assertTrue($this->values()->bool($db->value('SELECT CAST(:flag AS boolean)', ['flag' => true])));
        self::assertNull($db->value('SELECT CAST(:nothing AS bigint)', ['nothing' => null]));
        self::assertSame('app_complaint', $db->value('SELECT CAST(:type AS varchar)', ['type' => DocumentType::AppComplaint]));
    }

    public function testAppealDaysAreReadInModuleTimeZone(): void
    {
        $period = $this->values()->daysOrNull('2026-10-15', '2026-10-19');

        self::assertSame('2026-10-15 00:00:00+05:00', $period?->startsAt->format('Y-m-d H:i:sP'));
        self::assertSame('2026-10-20 00:00:00+05:00', $period?->endsAt->format('Y-m-d H:i:sP'));
        self::assertNull($this->values()->daysOrNull(null, '2026-10-19'));
        self::assertSame('2026-10-20', $this->values()->dayOf(new DateTimeImmutable('2026-10-19 19:30:00+00:00')));
    }

    public function testReferenceIdsMatchReferenceTables(): void
    {
        $db = new Database($this->pdo);
        $expected = [];
        foreach ((new ReflectionClass(ReferenceIds::class))->getConstants() as $name => $value) {
            if (!is_int($value)) {
                continue;
            }
            foreach (self::REFERENCE_TABLES as $prefix => $table) {
                if (str_starts_with($name, $prefix)) {
                    $expected[$table][] = $value;
                    break;
                }
            }
        }

        foreach ($expected as $table => $ids) {
            sort($ids);
            $actual = array_map('intval', array_column($db->rows('SELECT id FROM ' . $table . ' ORDER BY id'), 'id'));
            self::assertSame($ids, $actual, $table);
        }
        self::assertCount(count(self::REFERENCE_TABLES), $expected);
    }

    public function testNestedTransactionRollsBackOnlyItsSavepoint(): void
    {
        $runner = new PdoTransactionRunner($this->pdo);
        $db = new Database($this->pdo);
        $insert = "INSERT INTO trd_buy (id, number_anno, name_ru, name_kz, method_id, organizer_org_id, published_at)"
            . " VALUES (:id, 'A', 'a', 'a', 2, 900, now())";

        $runner->transactional(function () use ($runner, $db, $insert): void {
            $db->execute($insert, ['id' => 7]);
            try {
                $runner->transactional(function () use ($db, $insert): void {
                    $db->execute($insert, ['id' => 8]);
                    throw new RuntimeException('откат внутреннего действия');
                });
            } catch (RuntimeException) {
            }
        });

        self::assertSame(['7'], array_map('strval', array_column($db->rows('SELECT id FROM trd_buy WHERE id IN (7, 8)'), 'id')));
    }

    public function testSchemaNamesMustBeIdentifiers(): void
    {
        $config = self::schemaConfig();
        $config['buy']['appeal_end'] = 'appeal_end_date; DROP TABLE x';

        $this->expectException(InvalidArgumentException::class);
        ProjectSchema::fromConfig($config);
    }

    public function testOneDecisionPerBuyAndAppealPeriod(): void
    {
        $decisionId = $this->decision();
        $timeZone = new DateTimeZone('Asia/Almaty');

        $again = $this->decisions()->create(new BuyComplaintCycle(
            1,
            500,
            Period::ofDays('2026-10-15', '2026-10-19', $timeZone),
            Period::ofDays('2026-10-20', '2026-10-22', $timeZone),
        ), new DateTimeImmutable('2026-10-20 00:15:00+05:00'));
        $decision = $this->decisions()->get($decisionId);

        self::assertNull($again);
        self::assertSame(500, $decision->authorityOrgId);
        self::assertSame(BuyComplaintStatus::InReview, $decision->status);
        self::assertSame('2026-10-22 23:59', $decision->reviewPeriod->displayEndsAt()->format('Y-m-d H:i'));
        self::assertSame(
            [['Срок обжалования', 1], ['Срок рассмотрения', 2]],
            array_map(
                static fn (array $row): array => [$row['name_ru'], (int) $row['date_type_id']],
                (new Database($this->pdo))->rows('SELECT name_ru, date_type_id FROM trd_complaint_date WHERE trd_complaint_id = :id ORDER BY date_type_id', ['id' => $decisionId]),
            ),
        );
    }

    public function testRouteMembersHaveOneRowPerRole(): void
    {
        $decisions = $this->decisions();
        $decisionId = $this->decision();
        $now = new DateTimeImmutable('2026-10-21 10:00:00+05:00');
        $reviewer = $this->actor(20, 500);

        self::assertTrue($decisions->claim($decisionId, $reviewer, $now));
        self::assertFalse($decisions->claim($decisionId, $this->actor(23, 500), $now), 'Решение уже закреплено.');
        $decisions->assignRoute($decisionId, $this->actor(21, 500), $this->actor(22, 500), $reviewer, $now);
        $decisions->assignRoute($decisionId, null, $this->actor(22, 500), $reviewer, $now);

        $decision = $decisions->get($decisionId);
        self::assertSame([20, null, 22], [$decision->reviewerUserId, $decision->coordinatorUserId, $decision->approverUserId]);
        self::assertSame(
            [[1, 20, 20], [3, 22, 20]],
            array_map(
                static fn (array $row): array => [(int) $row['ref_trd_complaint_role_id'], (int) $row['member_id'], (int) $row['assigned_by_user_id']],
                (new Database($this->pdo))->rows('SELECT * FROM trd_complaint_member WHERE trd_complaint_id = :id ORDER BY ref_trd_complaint_role_id', ['id' => $decisionId]),
            ),
        );

        $decisions->resetRoute($decisionId, $now);
        $decisions->release($decisionId, $now);
        self::assertSame([null, null, null], [
            $decisions->get($decisionId)->reviewerUserId,
            $decisions->get($decisionId)->coordinatorUserId,
            $decisions->get($decisionId)->approverUserId,
        ]);
    }

    public function testDocumentVersionsAndOneCurrentVersionPerOwner(): void
    {
        $tables = new DocsTables(new Database($this->pdo), $this->values());
        $documents = new SqlDocumentRepository($tables, $this->values());
        $decisionId = $this->decision();
        $reviewer = $this->actor(20, 500);
        $now = new DateTimeImmutable('2026-10-21 10:00:00+05:00');

        $first = $documents->create(DocumentType::ConsolidatedAnswer, $decisionId, 11, $reviewer, $now);
        $documents->retire($first->id, $now);
        $second = $documents->create(DocumentType::ConsolidatedAnswer, $decisionId, 12, $reviewer, $now);

        self::assertSame([11, 1, false], [$documents->get(11)->fileId, $documents->get(11)->version, $documents->get(11)->isCurrent]);
        self::assertSame([12, 2, true], [$second->id, $second->version, $second->isCurrent]);
        self::assertSame($second->id, $documents->current(DocumentType::ConsolidatedAnswer, $decisionId)?->id);
        self::assertSame(20, $second->generatedByUserId);

        $this->expectException(PDOException::class);
        (new PdoTransactionRunner($this->pdo))->transactional(
            fn () => $documents->create(DocumentType::ConsolidatedAnswer, $decisionId, 13, $reviewer, $now),
        );
    }

    public function testAttachmentsAreFilesOfTheirOwner(): void
    {
        $tables = new DocsTables(new Database($this->pdo), $this->values());
        $attachments = new SqlAttachmentRepository($tables, $this->values());
        $documents = new SqlDocumentRepository($tables, $this->values());
        $now = new DateTimeImmutable('2026-10-16 11:00:00+05:00');
        $complaintId = (int) $this->pdo->query(
            'INSERT INTO app_complaint (trd_buy_id, trd_app_id, director_id, cruser_id, crsubject_id) VALUES (1, 1, 11, 10, 100) RETURNING id',
        )->fetchColumn();

        self::assertSame(31, $attachments->add(AttachmentOwner::AppComplaint, $complaintId, 31, $this->actor(10, 100), $now));
        $attachment = $attachments->get(31);
        self::assertSame([AttachmentOwner::AppComplaint, $complaintId, 10], [$attachment->owner, $attachment->ownerId, $attachment->uploadedByUserId]);
        self::assertCount(1, $attachments->of(AttachmentOwner::AppComplaint, $complaintId));

        try {
            $documents->get(31);
            self::fail('Вложение — не документ.');
        } catch (NotFound) {
        }

        $attachments->remove(31, $now);
        self::assertSame([], $attachments->of(AttachmentOwner::AppComplaint, $complaintId));
        $this->expectException(NotFound::class);
        $attachments->get(31);
    }

    public function testEligibilityChecksConfiguredStatuses(): void
    {
        $db = new Database($this->pdo);
        $eligibility = static fn (array $appStatuses, array $lotStatuses): StatusApplicationEligibility => new StatusApplicationEligibility(
            $db,
            'trd_app',
            'status_id',
            $appStatuses,
            'trd_app_lot',
            'status_id',
            $lotStatuses,
        );

        self::assertTrue($eligibility([], [])->isEligible(1, 1), 'Пустые списки — любой статус.');
        self::assertTrue($eligibility([1], [1])->isEligible(1, 1));
        self::assertFalse($eligibility([5], [])->isEligible(1, 1));
        self::assertFalse($eligibility([], [5])->isEligible(1, 1));
    }
}
