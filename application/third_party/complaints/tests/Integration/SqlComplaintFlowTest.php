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
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\SignatureRepository;
use Complaints\Application\Shared\UploadedFile;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Signature\SignatureStatus;
use Complaints\Tests\Support\ReviewSteps;
use Complaints\Tests\Support\World;
use DateTimeImmutable;
use PDO;

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
     * @return list<string>
     */
    private function trail(string $entityType, int $entityId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT to_status FROM test_complaint_status_history WHERE entity_type = :type AND entity_id = :id ORDER BY id',
        );
        $statement->execute(['type' => $entityType, 'id' => $entityId]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    public function testSupplierLifecycleIsStoredWithHistoryAndSnapshot(): void
    {
        $id = $this->world->draft();
        $this->world->get(ChangeDraftLot::class)->execute($id, World::APP_LOT_B);
        $this->world->get(ChangeDraftLot::class)->execute($id, World::APP_LOT_A);
        $this->world->get(AddComplaintAttachment::class)->execute($id, new UploadedFile('РУ.pdf', 'application/pdf', 'bytes'));
        $this->world->get(SendComplaintForSignature::class)->execute($id);

        $complaint = $this->complaints()->get($id);
        self::assertSame(AppComplaintStatus::AwaitingSignature, $complaint->status);
        self::assertMatchesRegularExpression('/^Ж-\d{6}$/', $complaint->number->value);
        self::assertSame('2026-10-20 00:00:00+05:00', $complaint->appealPeriod->endsAt->format('Y-m-d H:i:sP'));
        self::assertSame('ТОО «Поставщик-1»', $complaint->requisitesSnapshot['supplier']['name']['ru'] ?? null);
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

        self::assertSame(
            ['draft', 'awaiting_signature', 'draft', 'awaiting_signature', 'signed', 'submitted', 'withdrawn', 'submitted'],
            $this->trail('app_complaint', $id),
        );

        $registry = $this->world->get(ListOrganizationComplaints::class)->execute(1);
        self::assertSame(1, $registry->total);
        self::assertSame('4889720-Т1', $registry->items[0]->row->lotNumber);
        self::assertNotNull($registry->items[0]->row->headSignedAt);
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
        self::assertSame('Р-125407-1', $decision->decisionNumber);
        self::assertSame(ExecutionStatus::InProgress, $decision->executionStatus);
        self::assertSame('2026-10-23 23:59', $decision->executionPeriod?->displayEndsAt()->format('Y-m-d H:i'));
        self::assertSame(AppComplaintStatus::Reviewed, $this->complaints()->get($second)->status);
        self::assertNotNull($this->complaints()->lotOf($second)->reviewedAt);
        self::assertSame(['in_review', 'on_coordination', 'on_approval', 'approved'], $this->trail('buy_complaint', $decisionId));

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

        // Новый протокол по лоту А — новый срок обжалования и новый цикл решения.
        $this->world->gateway->protocols[World::LOT_A] = new DateTimeImmutable('2026-10-26 18:00:00+05:00');
        $this->world->at('2026-10-27 10:00:00+05:00');
        $again = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $this->world->at('2026-10-30 00:10:00+05:00');
        $this->world->get(CollectSubmittedComplaints::class)->execute();
        $next = $this->steps->decisionOf($again);
        $this->steps->decide([$again => Verdict::Rejected]);

        self::assertNotSame($decisionId, $next);
        self::assertSame('Р-125407-1/2', $this->decisions()->get($next)->decisionNumber);
        self::assertSame(ExecutionStatus::NotRequired, $this->decisions()->get($next)->executionStatus);
    }

    public function testMethodChangeAndRecallAreReflectedInStorage(): void
    {
        $first = $this->world->submitted();
        $second = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $this->world->collect();
        $decisionId = $this->steps->decisionOf($first);

        $this->steps->chooseMethod($decisionId);
        $this->steps->answerAll([$first => Verdict::Satisfied, $second => Verdict::Rejected], [World::LOT_A => DecisionType::CancelPurchase]);
        $answerId = (int) $this->steps->answerOf($first);

        $this->steps->chooseMethod($decisionId, ReviewMethod::PerLot);
        self::assertNull($this->steps->answerOf($first));
        self::assertNull($this->world->get(DocumentRepository::class)->current(DocumentType::Answer, $answerId));
        self::assertSame(Verdict::Satisfied, $this->complaints()->lotOf($first)->verdict);
        self::assertSame([], $this->world->get(AnswerRepository::class)->ofBuyComplaintLot($this->steps->decisionLotOf($first)));

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

        $this->steps->send($decisionId);
        $consolidated = $this->world->get(DocumentRepository::class)->current(DocumentType::ConsolidatedAnswer, $decisionId);
        self::assertNotNull($consolidated);
        $this->world->get(RecallDecision::class)->execute($decisionId);

        self::assertSame(BuyComplaintStatus::Recalled, $this->decisions()->get($decisionId)->status);
        self::assertSame(
            [SignatureStatus::Annulled, SignatureStatus::Annulled, SignatureStatus::Annulled],
            array_map(static fn ($s) => $s->status, $this->world->get(SignatureRepository::class)->ofDocument($consolidated->id)),
        );

        $this->steps->send($decisionId);
        self::assertSame(2, $this->world->get(DocumentRepository::class)->current(DocumentType::ConsolidatedAnswer, $decisionId)?->version);
        self::assertSame(BuyComplaintStatus::OnCoordination, $this->decisions()->get($decisionId)->status);
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
