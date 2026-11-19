<?php

declare(strict_types=1);

namespace Complaints\Tests\Integration;

use Complaints\Application\AppComplaint\ComplaintAction;
use Complaints\Application\AppComplaint\UseCase\AddComplaintAttachment;
use Complaints\Application\AppComplaint\UseCase\ChangeDraftLot;
use Complaints\Application\AppComplaint\UseCase\DeleteComplaint;
use Complaints\Application\AppComplaint\UseCase\ListOrganizationComplaints;
use Complaints\Application\AppComplaint\UseCase\ReturnComplaintToDraft;
use Complaints\Application\AppComplaint\UseCase\SendComplaintForSignature;
use Complaints\Application\AppComplaint\UseCase\ShowComplaint;
use Complaints\Application\AppComplaint\UseCase\SignComplaint;
use Complaints\Application\AppComplaint\UseCase\SubmitComplaint;
use Complaints\Application\AppComplaint\UseCase\WithdrawComplaint;
use Complaints\Application\BuyComplaint\UseCase\AddAnswerAttachment;
use Complaints\Application\BuyComplaint\UseCase\ListAuthorityDecisions;
use Complaints\Application\BuyComplaint\UseCase\RecallDecision;
use Complaints\Application\BuyComplaint\UseCase\SaveLotAnswers;
use Complaints\Application\BuyComplaint\UseCase\SaveLotAnswersCommand;
use Complaints\Application\BuyComplaint\UseCase\ShowDecision;
use Complaints\Application\Collection\CollectSubmittedComplaints;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Execution\UseCase\CancelDecisionLots;
use Complaints\Application\Execution\UseCase\CompleteLotRevisions;
use Complaints\Application\Execution\UseCase\ListOrganizerDecisions;
use Complaints\Application\Execution\UseCase\StartLotsRevision;
use Complaints\Application\File\FileKind;
use Complaints\Application\File\UseCase\DownloadFile;
use Complaints\Application\Notification\SendDeadlineReminders;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Shared\UploadedFile;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Document\AttachmentOwner;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Signature\SignatureStatus;
use Complaints\Infrastructure\Persistence\PdoTransactionRunner;
use Complaints\Tests\Support\ReviewSteps;
use Complaints\Tests\Support\World;
use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * Сценарии модуля целиком на PostgreSQL: ограничения схемы, условные
 * переходы и запросы реестров проверяются вместе с логикой.
 */
final class SqlComplaintFlowTest extends SqlTestCase
{
    private World $world;
    private ReviewSteps $steps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->world = $this->world();
        $this->steps = new ReviewSteps($this->world);
    }

    private function complaints(): AppComplaintRepository
    {
        return $this->world->get(AppComplaintRepository::class);
    }

    private function decisions(): BuyComplaintRepository
    {
        return $this->world->get(BuyComplaintRepository::class);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Выражение отклоняется ограничением схемы; транзакция теста продолжается.
     *
     * @param array<string, mixed> $params
     */
    private function assertRejectedBySchema(string $constraint, string $sql, array $params = []): void
    {
        try {
            (new PdoTransactionRunner($this->pdo))->transactional(function () use ($sql, $params): void {
                $this->pdo->prepare($sql)->execute($params);
            });
            self::fail('Ожидалось нарушение ' . $constraint);
        } catch (PDOException $error) {
            self::assertStringContainsString($constraint, $error->getMessage());
        }
    }

    public function testSupplierLifecycleIsStored(): void
    {
        $id = $this->world->draft();
        $this->world->get(ChangeDraftLot::class)->execute($id, World::APP_LOT_B);
        $this->world->get(ChangeDraftLot::class)->execute($id, World::APP_LOT_A);
        $this->world->get(AddComplaintAttachment::class)->execute($id, new UploadedFile('РУ.pdf', 'application/pdf', 'bytes'));
        $this->world->get(SendComplaintForSignature::class)->execute($id);

        $complaint = $this->complaints()->get($id);
        self::assertSame(AppComplaintStatus::AwaitingSignature, $complaint->status);
        self::assertSame(sprintf('Ж-%06d', $id), $complaint->number->value);
        self::assertSame('2026-10-20 00:00:00+05:00', $complaint->appealPeriod->endsAt->format('Y-m-d H:i:sP'));
        self::assertSame(World::LOT_A, $this->complaints()->lotOf($id)->buyLotId);

        $this->world->get(ReturnComplaintToDraft::class)->execute($id);
        $this->world->get(SendComplaintForSignature::class)->execute($id);
        self::assertSame(2, $this->world->get(DocumentRepository::class)->current(DocumentType::AppComplaint, $id)?->version);

        $this->world->actAs(World::HEAD);
        $this->world->get(SignComplaint::class)->execute($id, base64_encode('cms'));
        $this->world->actAs(World::AUTHOR);
        $this->world->get(SubmitComplaint::class)->execute($id);
        $this->world->get(WithdrawComplaint::class)->execute($id);
        $this->world->get(SubmitComplaint::class)->execute($id);

        $row = $this->rows('SELECT * FROM app_complaint WHERE id = :id', ['id' => $id])[0];
        self::assertSame(
            [4, World::HEAD, World::AUTHOR, World::SUPPLIER, World::AUTHOR, '+7 701 123 45 67'],
            [(int) $row['status_id'], (int) $row['director_id'], (int) $row['cruser_id'], (int) $row['crsubject_id'],
                (int) $row['applyed_by_user_id'], $row['phone_number']],
        );
        self::assertNotNull($row['date_apply']);
        self::assertSame(
            [[1, true], [1, false], [2, true]],
            array_map(
                static fn (array $doc): array => [(int) $doc['ref_app_complaint_docs_type_id'], $doc['deleted_at'] === null],
                $this->rows('SELECT * FROM app_complaint_docs WHERE app_complaint_id = :id ORDER BY ref_app_complaint_docs_type_id, deleted_at NULLS FIRST', ['id' => $id]),
            ),
        );

        $registry = $this->world->get(ListOrganizationComplaints::class)->execute(1);
        self::assertSame(1, $registry->total);
        self::assertSame('4889720-Т1', $registry->items[0]->row->lotNumber);
        self::assertNotNull($registry->items[0]->row->complaint->submittedAt);
        self::assertSame([ComplaintAction::Withdraw], $registry->items[0]->actions);

        $card = $this->world->get(ShowComplaint::class)->execute($id);
        self::assertCount(1, $card->attachments);
        self::assertSame(SignatureStatus::Signed, $card->signatures[0]->signature->status);
    }

    public function testDuplicateAndDeletedComplaints(): void
    {
        $draft = $this->world->draft();
        try {
            $this->world->draft();
            self::fail('Вторая жалоба на тот же лот заявки в том же сроке запрещена.');
        } catch (ValidationFailed $error) {
            self::assertSame('complaints_error_duplicate_complaint', $error->messageKey());
        }

        $this->world->get(DeleteComplaint::class)->execute($draft);
        $again = $this->world->draft();

        self::assertNotSame($draft, $again);
        self::assertSame([], $this->world->get(ListOrganizationComplaints::class)->execute(2)->items);
        self::assertSame(1, $this->world->get(ListOrganizationComplaints::class)->execute(1)->total);
    }

    public function testCollectionWaitsForTheLastAppealDayAndGracePeriod(): void
    {
        $id = $this->world->submitted();

        $this->world->at('2026-10-20 00:04:59+05:00');
        self::assertSame(0, $this->world->get(CollectSubmittedComplaints::class)->execute()->complaintsCollected);
        $this->world->at('2026-10-20 00:05:00+05:00');
        self::assertSame(1, $this->world->get(CollectSubmittedComplaints::class)->execute()->complaintsCollected);
        self::assertSame(AppComplaintStatus::InReview, $this->complaints()->get($id)->status);
    }

    public function testCollectionReviewApprovalExecutionAndNextCycle(): void
    {
        $first = $this->world->submitted();
        $second = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $third = $this->world->submitted(appLotId: World::APP_LOT_B);
        $this->world->collect();
        $report = $this->world->get(CollectSubmittedComplaints::class)->execute();
        self::assertSame(0, $report->complaintsCollected, 'Повторный сбор ничего не меняет.');

        $decisionId = $this->steps->decisionOf($first);
        self::assertSame($decisionId, $this->steps->decisionOf($third));
        self::assertSame($this->steps->decisionLotOf($first), $this->steps->decisionLotOf($second));

        $this->world->actAs(World::REVIEWER);
        $overview = $this->world->get(ShowDecision::class)->execute($decisionId);
        self::assertSame([World::LOT_A, World::LOT_B], array_map(static fn ($row): int => $row->lot->buyLotId, $overview->lots));
        self::assertSame([2, 1], array_map(static fn ($row): int => $row->complaintsCount, $overview->lots));

        $this->world->at('2026-10-21 14:21:00+05:00');
        $this->steps->decide(
            [$first => Verdict::Satisfied, $second => Verdict::Rejected, $third => Verdict::Satisfied],
            [World::LOT_A => DecisionType::ReviseResults, World::LOT_B => DecisionType::CancelPurchase],
        );

        $decision = $this->decisions()->get($decisionId);
        self::assertSame(BuyComplaintStatus::Approved, $decision->status);
        self::assertSame(ExecutionStatus::InProgress, $decision->executionStatus);
        self::assertSame('2026-10-23 23:59', $decision->executionPeriod?->displayEndsAt()->format('Y-m-d H:i'));
        self::assertSame(AppComplaintStatus::Reviewed, $this->complaints()->get($second)->status);
        self::assertEquals($decision->approvedAt, $this->complaints()->lotOf($second)->reviewedAt);

        $row = $this->rows('SELECT * FROM trd_complaint WHERE id = :id', ['id' => $decisionId])[0];
        self::assertSame(
            [6, 1, 2, 3, World::AUTHORITY, World::REVIEWER],
            [(int) $row['status_id'], (int) $row['method_id'], (int) $row['lots_count'], (int) $row['app_complaint_count'],
                (int) $row['crsubject_id'], (int) $row['sent_by_user_id']],
        );
        self::assertNotNull($row['coordinated_at']);
        self::assertSame(
            [1, 2, 3],
            array_map('intval', array_column($this->rows('SELECT date_type_id FROM trd_complaint_date WHERE trd_complaint_id = :id ORDER BY 1', ['id' => $decisionId]), 'date_type_id')),
        );
        self::assertSame(
            [[1, World::REVIEWER], [2, World::COORDINATOR], [3, World::APPROVER]],
            array_map(
                static fn (array $member): array => [(int) $member['ref_trd_complaint_role_id'], (int) $member['member_id']],
                $this->rows('SELECT * FROM trd_complaint_member WHERE trd_complaint_id = :id ORDER BY 1', ['id' => $decisionId]),
            ),
        );

        $answerDocument = $this->world->get(DocumentRepository::class)->current(DocumentType::Answer, (int) $this->steps->answerOf($first));
        self::assertNotNull($answerDocument);
        self::assertSame($answerDocument->id, $this->world->get(DocumentRepository::class)->get($answerDocument->id)->id);
        $this->world->actAs(World::AUTHOR);
        self::assertStringStartsWith('%PDF-answer', $this->world->get(DownloadFile::class)->execute(FileKind::Document, $answerDocument->id)->bytes);
        $this->world->actAs(World::SUPPLIER2_AUTHOR);
        try {
            $this->world->get(DownloadFile::class)->execute(FileKind::Document, $answerDocument->id);
            self::fail('Ответ на чужую жалобу не выдаётся.');
        } catch (AccessDenied) {
        }

        $this->world->actAs(World::REVIEWER);
        $authorityRegistry = $this->world->get(ListAuthorityDecisions::class)->execute(BuyComplaintStatus::Approved, '125407-1', 1);
        self::assertSame(1, $authorityRegistry->total);
        self::assertSame(3, $authorityRegistry->items[0]->row->complaintsCount);
        self::assertSame([DecisionType::CancelPurchase, DecisionType::ReviseResults], $authorityRegistry->items[0]->row->decisionTypes);

        $this->world->at('2026-10-23 09:00:00+05:00');
        self::assertSame(1, $this->world->get(SendDeadlineReminders::class)->execute());

        $this->world->actAs(World::ORGANIZER);
        self::assertSame(1, $this->world->get(ListOrganizerDecisions::class)->execute(ExecutionStatus::InProgress, null, 1)->total);
        self::assertSame(1, $this->world->get(CancelDecisionLots::class)->execute($decisionId));
        self::assertSame(1, $this->world->get(StartLotsRevision::class)->execute($decisionId));
        self::assertSame(
            LotExecutionStatus::Started,
            $this->decisions()->lot($this->steps->decisionLotOf($first))->executionStatus,
        );

        $this->world->gateway->revisionsCompleted[World::LOT_A] = new DateTimeImmutable('2026-10-26 18:00:00+05:00');
        self::assertSame(1, $this->world->get(CompleteLotRevisions::class)->execute());
        self::assertSame(ExecutionStatus::Executed, $this->decisions()->get($decisionId)->executionStatus);
        self::assertSame('2026-10-26 18:00', $this->decisions()->get($decisionId)->executedAt?->format('Y-m-d H:i'));
        self::assertSame(
            [[World::ORGANIZER, World::ORGANIZER_ORG, true], [World::ORGANIZER, World::ORGANIZER_ORG, true]],
            array_map(
                static fn (array $lot): array => [(int) $lot['execution_started_by_user_id'], (int) $lot['execution_started_by_subject_id'], $lot['executed_at'] !== null],
                $this->rows('SELECT * FROM trd_complaint_lots WHERE trd_complaint_id = :id ORDER BY id', ['id' => $decisionId]),
            ),
        );

        // Новый протокол итогов — проект установил закупке новый срок обжалования: новый цикл решения.
        $this->setAppealDays('2026-10-27', '2026-10-29');
        $this->world->at('2026-10-27 10:00:00+05:00');
        $again = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $this->world->at('2026-10-30 00:10:00+05:00');
        $this->world->get(CollectSubmittedComplaints::class)->execute();
        $next = $this->steps->decisionOf($again);
        $this->steps->decide([$again => Verdict::Rejected]);

        self::assertNotSame($decisionId, $next);
        self::assertSame('2026-10-30 00:00', $this->decisions()->get($next)->appealPeriod->endsAt->format('Y-m-d H:i'));
        self::assertSame('2026-10-20 00:00', $this->complaints()->get($first)->appealPeriod->endsAt->format('Y-m-d H:i'), 'У собранной жалобы — срок её решения.');
        self::assertSame(ExecutionStatus::NotRequired, $this->decisions()->get($next)->executionStatus);
    }

    public function testMethodChangeAndRecallAreReflectedInStorage(): void
    {
        $first = $this->world->submitted();
        $second = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $this->world->collect();
        $decisionId = $this->steps->decisionOf($first);

        $this->steps->chooseMethod($decisionId);
        $this->world->get(AddAnswerAttachment::class)->execute(
            $this->steps->decisionLotOf($first),
            $this->steps->complaintLotOf($first),
            new UploadedFile('Заключение.pdf', 'application/pdf', 'x'),
        );
        $this->steps->answerAll([$first => Verdict::Satisfied, $second => Verdict::Rejected], [World::LOT_A => DecisionType::CancelPurchase]);
        $answerId = (int) $this->steps->answerOf($first);
        $perComplaintFile = $this->world->get(AttachmentRepository::class)->of(AttachmentOwner::Answer, $answerId);
        self::assertCount(1, $perComplaintFile);
        self::assertSame(
            [[4, $this->steps->complaintLotOf($first)]],
            array_map(
                static fn (array $doc): array => [(int) $doc['ref_app_complaint_docs_type_id'], (int) $doc['app_complaint_lots_id']],
                $this->rows('SELECT * FROM app_complaint_docs WHERE fls_stg_entity_id = :file', ['file' => $perComplaintFile[0]->fileId]),
            ),
        );

        $this->steps->chooseMethod($decisionId, ReviewMethod::PerLot);
        self::assertNull($this->steps->answerOf($first));
        self::assertNull($this->world->get(DocumentRepository::class)->current(DocumentType::Answer, $answerId));
        self::assertNotNull($this->rows('SELECT deleted_at FROM app_complaint_docs WHERE fls_stg_entity_id = :file', ['file' => $perComplaintFile[0]->fileId])[0]['deleted_at']);
        self::assertSame(Verdict::Satisfied, $this->complaints()->lotOf($first)->verdict);
        self::assertSame([], $this->world->get(AnswerRepository::class)->ofBuyComplaintLot($this->steps->decisionLotOf($first)));

        $lotFile = $this->world->get(AddAnswerAttachment::class)->execute(
            $this->steps->decisionLotOf($first),
            null,
            new UploadedFile('Протокол.pdf', 'application/pdf', 'y'),
        );
        self::assertSame(
            [[3, $this->steps->decisionLotOf($first)]],
            array_map(
                static fn (array $doc): array => [(int) $doc['ref_trd_complaint_docs_type_id'], (int) $doc['trd_complaint_lots_id']],
                $this->rows('SELECT * FROM trd_complaint_docs WHERE fls_stg_entity_id = :file', ['file' => $lotFile]),
            ),
        );
        self::assertSame(AttachmentOwner::Answer, $this->world->get(AttachmentRepository::class)->get($lotFile)->owner);
        self::assertSame($this->steps->answerOf($second), $this->world->get(AttachmentRepository::class)->get($lotFile)->ownerId);

        $this->world->get(SaveLotAnswers::class)->execute(
            new SaveLotAnswersCommand(
                $this->steps->decisionLotOf($first),
                [$this->steps->complaintLotOf($first) => Verdict::Satisfied, $this->steps->complaintLotOf($second) => Verdict::Rejected],
                [],
                'Один ответ на обе жалобы лота',
                DecisionType::CancelPurchase,
            ),
        );
        $this->steps->formAnswers([$first]);
        self::assertSame($this->steps->answerOf($first), $this->steps->answerOf($second));

        $lotAnswer = $this->world->get(DocumentRepository::class)->current(DocumentType::Answer, (int) $this->steps->answerOf($first));
        self::assertNotNull($lotAnswer);
        self::assertSame(
            [[2, $this->steps->decisionLotOf($first)]],
            array_map(
                static fn (array $doc): array => [(int) $doc['ref_trd_complaint_docs_type_id'], (int) $doc['trd_complaint_lots_id']],
                $this->rows('SELECT * FROM trd_complaint_docs WHERE fls_stg_entity_id = :file', ['file' => $lotAnswer->fileId]),
            ),
        );
        self::assertSame(
            [3, 3],
            array_map('intval', array_column($this->rows(
                'SELECT ref_app_complaint_docs_type_id FROM app_complaint_docs WHERE ref_app_complaint_docs_type_id = 3 AND deleted_at IS NOT NULL',
            ), 'ref_app_complaint_docs_type_id')),
            'PDF ответов по каждой жалобе сняты при смене способа.',
        );

        $this->steps->send($decisionId);
        $consolidated = $this->world->get(DocumentRepository::class)->current(DocumentType::ConsolidatedAnswer, $decisionId);
        self::assertNotNull($consolidated);
        $this->world->actAs(World::REVIEWER);
        $this->world->get(RecallDecision::class)->execute($decisionId);

        $recalled = $this->decisions()->get($decisionId);
        self::assertSame(BuyComplaintStatus::Recalled, $recalled->status);
        self::assertSame([World::REVIEWER, null, null], [$recalled->reviewerUserId, $recalled->coordinatorUserId, $recalled->approverUserId]);
        self::assertSame([null, World::REVIEWER], [$recalled->sentForApprovalAt, $recalled->recalledByUserId]);
        self::assertNull($this->world->get(DocumentRepository::class)->current(DocumentType::ConsolidatedAnswer, $decisionId));
        self::assertFalse($this->world->get(DocumentRepository::class)->get($consolidated->id)->isCurrent);

        $this->steps->send($decisionId);
        self::assertSame(2, $this->world->get(DocumentRepository::class)->current(DocumentType::ConsolidatedAnswer, $decisionId)?->version);
        self::assertSame(BuyComplaintStatus::OnCoordination, $this->decisions()->get($decisionId)->status);
    }

    public function testSchemaRejectsInconsistentRows(): void
    {
        $first = $this->world->submitted();
        $second = $this->world->submitted(appLotId: World::APP_LOT_B);
        $this->world->collect();
        $this->steps->chooseMethod($this->steps->decisionOf($first));
        $this->steps->answerAll([$first => Verdict::Satisfied, $second => Verdict::Rejected]);

        $this->assertRejectedBySchema(
            'fk_app_complaint_lots_answer',
            'UPDATE app_complaint_lots SET answer_id = :answer WHERE id = :lot',
            ['answer' => $this->steps->answerOf($second), 'lot' => $this->steps->complaintLotOf($first)],
        );
        $this->assertRejectedBySchema(
            'fk_app_complaint_lots_trd_lot',
            'UPDATE app_complaint_lots SET trd_complaint_lots_id = :lot, answer_id = NULL WHERE id = :complaint_lot',
            ['lot' => $this->steps->decisionLotOf($second), 'complaint_lot' => $this->steps->complaintLotOf($first)],
        );
        $this->assertRejectedBySchema(
            'chk_app_complaint_collected',
            'UPDATE app_complaint SET trd_complaint_id = NULL WHERE id = :id',
            ['id' => $first],
        );
        $this->assertRejectedBySchema(
            'chk_app_complaint_deleted_before_submission',
            'UPDATE app_complaint SET deleted_at = now() WHERE id = :id',
            ['id' => $first],
        );
        $this->assertRejectedBySchema(
            'chk_trd_complaint_sent',
            'UPDATE trd_complaint SET status_id = 3 WHERE id = :id',
            ['id' => $this->steps->decisionOf($first)],
        );
    }

    public function testFilesAreServedByModuleRights(): void
    {
        $id = $this->world->submitted();
        $document = $this->world->get(DocumentRepository::class)->current(DocumentType::AppComplaint, $id);
        self::assertNotNull($document);

        $this->world->actAs(World::AUTHOR);
        $file = $this->world->get(DownloadFile::class)->execute(FileKind::Document, $document->id);
        self::assertStringStartsWith('%PDF-app_complaint', $file->bytes);

        $this->world->actAs(World::SUPPLIER2_AUTHOR);
        $this->expectException(AccessDenied::class);
        $this->world->get(DownloadFile::class)->execute(FileKind::Document, $document->id);
    }
}
