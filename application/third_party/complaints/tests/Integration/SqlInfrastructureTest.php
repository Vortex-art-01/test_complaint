<?php

declare(strict_types=1);

namespace Complaints\Tests\Integration;

use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Shared\Sha256;
use Complaints\Domain\Signature\SignerRole;
use Complaints\Infrastructure\Persistence\Database;
use Complaints\Infrastructure\Persistence\PdoTransactionRunner;
use Complaints\Infrastructure\Persistence\ProjectSchema;
use Complaints\Infrastructure\Persistence\SqlDocumentRepository;
use Complaints\Infrastructure\Persistence\SqlSignatureRepository;
use Complaints\Infrastructure\Persistence\SqlValues;
use Complaints\Infrastructure\System\StatusApplicationEligibility;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

final class SqlInfrastructureTest extends SqlTestCase
{
    private function values(): SqlValues
    {
        return new SqlValues(new DateTimeZone('Asia/Almaty'));
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

    public function testNestedTransactionRollsBackOnlyItsSavepoint(): void
    {
        $runner = new PdoTransactionRunner($this->pdo);
        $db = new Database($this->pdo);

        $runner->transactional(function () use ($runner, $db): void {
            $db->execute("INSERT INTO test_buy (id, number_anno, name_ru, name_kz, method_id, organizer_org_id, published_at) VALUES (7, 'A-7', 'a', 'a', 2, 900, now())");
            try {
                $runner->transactional(function () use ($db): void {
                    $db->execute("INSERT INTO test_buy (id, number_anno, name_ru, name_kz, method_id, organizer_org_id, published_at) VALUES (8, 'A-8', 'b', 'b', 2, 900, now())");
                    throw new RuntimeException('откат внутреннего действия');
                });
            } catch (RuntimeException) {
            }
        });

        self::assertSame(['7'], array_map('strval', array_column($db->rows('SELECT id FROM test_buy WHERE id IN (7, 8)'), 'id')));
    }

    public function testSchemaNamesMustBeIdentifiers(): void
    {
        $config = [
            'buy' => ['table' => 'test_buy; DROP TABLE x', 'number' => 'n', 'name_ru' => 'r', 'name_kz' => 'k', 'method' => 'm', 'organizer' => 'o', 'published_at' => 'p'],
            'buy_lot' => ['table' => 't', 'buy' => 'b', 'number' => 'n', 'name_ru' => 'r', 'name_kz' => 'k'],
            'app' => ['table' => 'a', 'supplier' => 's'],
            'app_lot' => ['table' => 'l'],
        ];

        $this->expectException(InvalidArgumentException::class);
        ProjectSchema::fromConfig($config);
    }

    public function testDocumentVersionsAndOneCurrentVersionPerOwner(): void
    {
        $documents = new SqlDocumentRepository(new Database($this->pdo), $this->values());
        $decisionId = $this->decision();
        $now = new DateTimeImmutable('2026-10-21 10:00:00+05:00');

        $first = $documents->create(DocumentType::ConsolidatedAnswer, $decisionId, 11, Sha256::ofBytes('v1'), 20, $now);
        $documents->retire($first->id);
        $second = $documents->create(DocumentType::ConsolidatedAnswer, $decisionId, 12, Sha256::ofBytes('v2'), 20, $now);

        self::assertSame([1, 2], [$first->version, $second->version]);
        self::assertSame($second->id, $documents->current(DocumentType::ConsolidatedAnswer, $decisionId)?->id);
        self::assertTrue(Sha256::ofBytes('v2')->equals($second->sha256));

        $this->expectException(PDOException::class);
        (new PdoTransactionRunner($this->pdo))->transactional(
            fn () => $documents->create(DocumentType::ConsolidatedAnswer, $decisionId, 13, Sha256::ofBytes('v3'), 20, $now),
        );
    }

    public function testSignatureRouteHasOneSignerPerRole(): void
    {
        $db = new Database($this->pdo);
        $documents = new SqlDocumentRepository($db, $this->values());
        $signatures = new SqlSignatureRepository($db, $this->values());
        $now = new DateTimeImmutable('2026-10-21 10:00:00+05:00');
        $document = $documents->create(DocumentType::ConsolidatedAnswer, $this->decision(), 11, Sha256::ofBytes('v1'), 20, $now);

        $reviewer = $signatures->request($document->id, SignerRole::Reviewer, 20, $now);
        $signatures->request($document->id, SignerRole::Approver, 22, $now);
        self::assertTrue($signatures->sign($reviewer, 99, 'CN=Сидорова', $now));
        self::assertFalse($signatures->sign($reviewer, 99, 'CN=Сидорова', $now), 'Подпись ставится один раз.');
        self::assertSame(2, $signatures->annulAll($document->id, 20, $now));
        self::assertSame(0, $signatures->annulAll($document->id, 20, $now));

        $this->expectException(PDOException::class);
        (new PdoTransactionRunner($this->pdo))->transactional(
            fn () => $signatures->request($document->id, SignerRole::Approver, 22, $now),
        );
    }

    public function testEligibilityChecksConfiguredStatuses(): void
    {
        $db = new Database($this->pdo);
        $eligibility = static fn (array $appStatuses, array $lotStatuses): StatusApplicationEligibility => new StatusApplicationEligibility(
            $db,
            'test_app',
            'status_id',
            $appStatuses,
            'test_app_lots',
            'status_id',
            $lotStatuses,
        );

        self::assertTrue($eligibility([], [])->isEligible(1, 1), 'Пустые списки — любой статус.');
        self::assertTrue($eligibility([1], [1])->isEligible(1, 1));
        self::assertFalse($eligibility([5], [])->isEligible(1, 1));
        self::assertFalse($eligibility([], [5])->isEligible(1, 1));
    }

    private function decision(): int
    {
        return (int) $this->pdo->query(<<<'SQL'
            INSERT INTO test_buy_complaint (buy_id, authority_org_id, protocol_published_at, appeal_starts_at, appeal_ends_at, review_starts_at, review_ends_at)
            VALUES (1, 500, '2026-10-14 18:00+05', '2026-10-15 00:00+05', '2026-10-20 00:00+05', '2026-10-20 00:00+05', '2026-10-23 00:00+05')
            RETURNING id
            SQL)->fetchColumn();
    }
}
