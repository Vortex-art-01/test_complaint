<?php

declare(strict_types=1);

namespace Complaints\Tests\Application;

use Complaints\Application\BuyComplaint\DecisionAction;
use Complaints\Application\BuyComplaint\UseCase\ApproveDecision;
use Complaints\Application\BuyComplaint\UseCase\ChooseReviewMethod;
use Complaints\Application\BuyComplaint\UseCase\CoordinateDecision;
use Complaints\Application\BuyComplaint\UseCase\FormConsolidatedDocument;
use Complaints\Application\BuyComplaint\UseCase\ListAuthorityDecisions;
use Complaints\Application\BuyComplaint\UseCase\RecallDecision;
use Complaints\Application\BuyComplaint\UseCase\ReleaseDecision;
use Complaints\Application\BuyComplaint\UseCase\ReopenDecision;
use Complaints\Application\BuyComplaint\UseCase\ResetAnswerDocument;
use Complaints\Application\BuyComplaint\UseCase\SaveLotAnswers;
use Complaints\Application\BuyComplaint\UseCase\SaveLotAnswersCommand;
use Complaints\Application\BuyComplaint\UseCase\SendDecisionCommand;
use Complaints\Application\BuyComplaint\UseCase\SendDecisionForApproval;
use Complaints\Application\BuyComplaint\UseCase\ShowDecision;
use Complaints\Application\BuyComplaint\UseCase\ShowDecisionLot;
use Complaints\Application\Collection\CollectSubmittedComplaints;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Service\SignatureService;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Shared\Period;
use Complaints\Domain\Signature\SignatureStatus;
use Complaints\Tests\Support\ReviewSteps;
use Complaints\Tests\Support\World;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Рассмотрение: жалобы поставщика-1 и поставщика-2 по лоту А,
 * жалоба поставщика-1 по лоту Б, собраны в решение 1.
 */
final class ReviewFlowTest extends TestCase
{
    private const DECISION = 1;

    private World $world;
    private ReviewSteps $steps;
    private int $first;
    private int $second;
    private int $third;

    protected function setUp(): void
    {
        $this->world = new World();
        $this->steps = new ReviewSteps($this->world);
        $this->first = $this->world->submitted();
        $this->second = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $this->third = $this->world->submitted(appLotId: World::APP_LOT_B);
        $this->world->collect();
    }

    /**
     * Все жалобы отвечены, PDF ответов сформированы.
     */
    private function answerEverything(): void
    {
        $this->steps->chooseMethod(self::DECISION);
        $this->steps->answerAll(
            [$this->first => Verdict::Satisfied, $this->second => Verdict::Rejected, $this->third => Verdict::Rejected],
            [World::LOT_A => DecisionType::ReviseResults],
        );
    }

    /**
     * @return list<SignatureStatus>
     */
    private function consolidatedSignatureStatuses(): array
    {
        $document = $this->world->documents->current(DocumentType::ConsolidatedAnswer, self::DECISION);
        self::assertNotNull($document);

        return array_map(static fn ($signature) => $signature->status, $this->world->get(SignatureService::class)->signatures($document));
    }

    public function testFirstReviewerActionClaimsDecisionUntilApproverReleasesIt(): void
    {
        $this->steps->chooseMethod(self::DECISION);
        self::assertSame(World::REVIEWER, $this->world->decisions->get(self::DECISION)->reviewerUserId);

        $this->world->actAs(World::SECOND_REVIEWER);
        try {
            $this->world->get(ChooseReviewMethod::class)->execute(self::DECISION, ReviewMethod::PerLot);
            self::fail('Решение закреплено за другим рассматривающим.');
        } catch (AccessDenied) {
        }

        $this->world->actAs(World::APPROVER);
        $this->world->get(ReleaseDecision::class)->execute(self::DECISION);
        self::assertNull($this->world->decisions->get(self::DECISION)->reviewerUserId);

        $this->world->actAs(World::SECOND_REVIEWER);
        $this->world->get(ChooseReviewMethod::class)->execute(self::DECISION, ReviewMethod::PerComplaint);
        self::assertSame(World::SECOND_REVIEWER, $this->world->decisions->get(self::DECISION)->reviewerUserId);
    }

    public function testDecisionCardAndRegistryShowActionsOfCurrentUser(): void
    {
        $this->world->actAs(World::REVIEWER);
        $overview = $this->world->get(ShowDecision::class)->execute(self::DECISION);
        self::assertSame([DecisionAction::ChooseMethod], $overview->actions);
        self::assertSame(3, $overview->complaintsCount());
        self::assertSame([], $overview->gaps, 'ФЛК показывается после выбора способа рассмотрения.');

        $registry = $this->world->get(ListAuthorityDecisions::class)->execute(null, ' 125407-1 ', 1);
        self::assertSame(1, $registry->total);
        self::assertSame('', $registry->items[0]->reviewerName);

        $this->steps->chooseMethod(self::DECISION);
        $overview = $this->world->get(ShowDecision::class)->execute(self::DECISION);
        self::assertSame([DecisionAction::ChooseMethod, DecisionAction::EditAnswers], $overview->actions);
        self::assertSame(['4889720-Т1', '4889721-Т1'], array_map(static fn ($gap): string => $gap->buyLotNumber, $overview->gaps));
        self::assertSame(
            'Сидорова Айгуль Болатовна',
            $this->world->get(ListAuthorityDecisions::class)->execute(BuyComplaintStatus::InReview, null, 1)->items[0]->reviewerName,
        );

        $this->world->actAs(World::APPROVER);
        self::assertSame([DecisionAction::Release], $this->world->get(ShowDecision::class)->execute(self::DECISION)->actions);

        $this->world->actAs(World::AUTHOR);
        $this->expectException(AccessDenied::class);
        $this->world->get(ShowDecision::class)->execute(self::DECISION);
    }

    public function testAnswersNeedReviewMethod(): void
    {
        $this->world->actAs(World::REVIEWER);

        $this->expectExceptionObject(ValidationFailed::field('form', 'complaints_error_method_required'));
        $this->world->get(SaveLotAnswers::class)->execute(
            new SaveLotAnswersCommand($this->steps->decisionLotOf($this->first), [], [], null, null),
        );
    }

    public function testFullReviewWithCoordinationAndApproval(): void
    {
        $this->answerEverything();
        self::assertSame(
            DecisionType::NoAction,
            $this->world->decisions->lot($this->steps->decisionLotOf($this->third))->decisionType,
        );

        $this->steps->send(self::DECISION);
        self::assertSame(BuyComplaintStatus::OnCoordination, $this->world->decisions->get(self::DECISION)->status);
        self::assertSame(
            [SignatureStatus::Signed, SignatureStatus::Pending, SignatureStatus::Pending],
            $this->consolidatedSignatureStatuses(),
        );
        self::assertSame([World::COORDINATOR], $this->world->notifier->recipientsOf(NotificationType::DecisionForCoordination->value));

        $this->world->at('2026-10-21 14:21:00+05:00');
        $this->steps->approve(self::DECISION);

        $decision = $this->world->decisions->get(self::DECISION);
        self::assertSame(BuyComplaintStatus::Approved, $decision->status);
        self::assertSame(World::REVIEWER, $decision->sentByUserId);
        self::assertSame(World::REVIEWER, $this->world->decisions->assignedBy[self::DECISION]['approver']->userId);
        self::assertSame(World::AUTHORITY, $this->world->decisions->members[self::DECISION]['coordinator']->organizationId);
        self::assertNotNull($decision->sentForApprovalAt);
        self::assertNotNull($decision->coordinatedAt);
        self::assertSame('2026-10-21 14:21', $decision->approvedAt?->format('Y-m-d H:i'));
        self::assertSame(ExecutionStatus::InProgress, $decision->executionStatus);
        self::assertSame('2026-10-23 23:59', $decision->executionPeriod?->displayEndsAt()->format('Y-m-d H:i'));
        self::assertSame(array_fill(0, 3, SignatureStatus::Signed), $this->consolidatedSignatureStatuses());

        foreach ([$this->first, $this->second, $this->third] as $id) {
            self::assertSame(AppComplaintStatus::Reviewed, $this->world->complaints->get($id)->status);
            self::assertNotNull($this->world->complaints->lotOf($id)->reviewedAt);
        }
        self::assertSame(LotExecutionStatus::Pending, $this->world->decisions->lot($this->steps->decisionLotOf($this->first))->executionStatus);
        self::assertNull($this->world->decisions->lot($this->steps->decisionLotOf($this->third))->executionStatus);

        self::assertSame(
            [World::SUPPLIER, World::SUPPLIER2, World::SUPPLIER],
            $this->world->notifier->recipientsOf(NotificationType::ComplaintAnswered->value, 'organization'),
        );
        self::assertSame([World::ORGANIZER_ORG], $this->world->notifier->recipientsOf(NotificationType::DecisionRequiresExecution->value, 'organization'));
        self::assertSame([World::REVIEWER], $this->world->notifier->recipientsOf(NotificationType::DecisionApproved->value));
    }

    public function testLotWithAllComplaintsRejectedGetsNoActionWhateverIsRequested(): void
    {
        $this->steps->chooseMethod(self::DECISION);

        $this->steps->answer([$this->third => Verdict::Rejected], DecisionType::CancelPurchase);

        $lotId = $this->steps->decisionLotOf($this->third);
        self::assertSame(DecisionType::NoAction, $this->world->decisions->lot($lotId)->decisionType);
        self::assertSame([DecisionType::NoAction], $this->world->get(ShowDecisionLot::class)->execute($lotId)->allowedDecisionTypes);
    }

    public function testFormedAnswerIsLockedUntilReset(): void
    {
        $this->steps->chooseMethod(self::DECISION);
        $this->steps->answer([$this->third => Verdict::Rejected]);
        $this->steps->formAnswers([$this->third]);
        $complaintLotId = $this->steps->complaintLotOf($this->third);
        $change = new SaveLotAnswersCommand(
            $this->steps->decisionLotOf($this->third),
            [$complaintLotId => Verdict::Rejected],
            [$complaintLotId => 'Уточнённый ответ'],
            null,
            null,
        );

        try {
            $this->world->get(SaveLotAnswers::class)->execute($change);
            self::fail('Ответ со сформированным PDF менять нельзя.');
        } catch (ValidationFailed $error) {
            self::assertSame(['answers.' . $complaintLotId], array_keys($error->errors()));
        }

        $answerId = (int) $this->steps->answerOf($this->third);
        $this->world->get(ResetAnswerDocument::class)->execute($answerId);
        $this->world->get(SaveLotAnswers::class)->execute($change);

        self::assertSame('Уточнённый ответ', $this->world->answers->get($answerId)->text);
        self::assertNull($this->world->documents->current(DocumentType::Answer, $answerId));
    }

    public function testChangingReviewMethodDropsAnswersButKeepsVerdictsAndTypes(): void
    {
        $this->steps->chooseMethod(self::DECISION);
        $this->steps->answer([$this->first => Verdict::Satisfied, $this->second => Verdict::Rejected], DecisionType::CancelPurchase);
        $this->steps->formAnswers([$this->first]);
        $answerId = (int) $this->steps->answerOf($this->first);

        $this->steps->chooseMethod(self::DECISION, ReviewMethod::PerLot);

        self::assertNull($this->steps->answerOf($this->first));
        self::assertNull($this->steps->answerOf($this->second));
        self::assertArrayNotHasKey($answerId, $this->world->answers->answers);
        self::assertNull($this->world->documents->current(DocumentType::Answer, $answerId));
        self::assertSame(Verdict::Satisfied, $this->world->complaints->lotOf($this->first)->verdict);
        $lotId = $this->steps->decisionLotOf($this->first);
        self::assertSame(DecisionType::CancelPurchase, $this->world->decisions->lot($lotId)->decisionType);

        $this->world->get(SaveLotAnswers::class)->execute(new SaveLotAnswersCommand(
            $lotId,
            [$this->steps->complaintLotOf($this->first) => Verdict::Satisfied, $this->steps->complaintLotOf($this->second) => Verdict::Rejected],
            [],
            'Один ответ на обе жалобы лота',
            DecisionType::CancelPurchase,
        ));
        self::assertNotNull($this->steps->answerOf($this->first));
        self::assertSame($this->steps->answerOf($this->first), $this->steps->answerOf($this->second));
    }

    public function testConsolidatedDocumentNeedsFormedAnswerForEveryComplaint(): void
    {
        $this->steps->chooseMethod(self::DECISION);
        $this->steps->answer([$this->first => Verdict::Satisfied, $this->second => Verdict::Rejected], DecisionType::ReviseResults);
        $this->steps->formAnswers([$this->first, $this->second]);
        $this->steps->answer([$this->third => Verdict::Rejected]);

        try {
            $this->world->get(FormConsolidatedDocument::class)->execute(self::DECISION);
            self::fail('ФЛК должен остановить формирование.');
        } catch (ValidationFailed $error) {
            self::assertSame('complaints_error_decision_incomplete', $error->messageKey());
            self::assertSame(['lots' => '4889721-Т1 (0 / 1)'], $error->params());
        }

        $this->steps->formAnswers([$this->third]);
        $this->world->get(FormConsolidatedDocument::class)->execute(self::DECISION);
        self::assertSame(1, $this->world->documents->current(DocumentType::ConsolidatedAnswer, self::DECISION)?->version);
    }

    public function testRouteConsistsOfDistinctAuthorityEmployeesInTheirRoles(): void
    {
        $this->answerEverything();
        $this->world->get(FormConsolidatedDocument::class)->execute(self::DECISION);

        try {
            $this->world->get(SendDecisionForApproval::class)->execute(
                new SendDecisionCommand(self::DECISION, World::APPROVER, World::REVIEWER, base64_encode('cms')),
            );
            self::fail('Маршрут должен проверяться.');
        } catch (ValidationFailed $error) {
            self::assertSame(['approver_user_id', 'coordinator_user_id'], array_keys($error->errors()));
        }

        self::assertSame(BuyComplaintStatus::InReview, $this->world->decisions->get(self::DECISION)->status);
        self::assertSame([SignatureStatus::Pending], $this->consolidatedSignatureStatuses(), 'Ждём только подпись рассматривающего.');
    }

    public function testWithoutCoordinatorDecisionGoesStraightToApprover(): void
    {
        $this->answerEverything();

        $this->steps->send(self::DECISION, null);

        self::assertSame(BuyComplaintStatus::OnApproval, $this->world->decisions->get(self::DECISION)->status);
        self::assertSame([SignatureStatus::Signed, SignatureStatus::Pending], $this->consolidatedSignatureStatuses());
        self::assertSame([World::APPROVER], $this->world->notifier->recipientsOf(NotificationType::DecisionForApproval->value));
    }

    public function testOnlyAssignedPeopleCoordinateAndApproveInTurn(): void
    {
        $this->answerEverything();
        $this->steps->send(self::DECISION);

        $this->world->actAs(World::APPROVER);
        try {
            $this->world->get(CoordinateDecision::class)->execute(self::DECISION, base64_encode('cms'));
            self::fail('Согласует только назначенный согласующий.');
        } catch (AccessDenied) {
        }
        try {
            $this->world->get(ApproveDecision::class)->execute(self::DECISION, base64_encode('cms'));
            self::fail('Утверждение — после согласования.');
        } catch (StatusConflict) {
        }

        $this->world->actAs(World::COORDINATOR);
        $this->world->get(CoordinateDecision::class)->execute(self::DECISION, base64_encode('cms'));

        $this->world->actAs(World::REVIEWER);
        $this->expectException(AccessDenied::class);
        $this->world->get(ApproveDecision::class)->execute(self::DECISION, base64_encode('cms'));
    }

    public function testRecallRetiresSignedVersionAndResetsRoute(): void
    {
        $this->answerEverything();
        $this->steps->send(self::DECISION);

        $this->world->get(RecallDecision::class)->execute(self::DECISION);

        $decision = $this->world->decisions->get(self::DECISION);
        self::assertSame(BuyComplaintStatus::Recalled, $decision->status);
        self::assertSame(World::REVIEWER, $decision->recalledByUserId);
        self::assertNotNull($decision->recalledAt);
        self::assertNull($decision->coordinatorUserId);
        self::assertNull($decision->approverUserId);
        self::assertNull($decision->sentForApprovalAt);
        self::assertNull($decision->coordinatedAt);
        self::assertNull($this->world->documents->current(DocumentType::ConsolidatedAnswer, self::DECISION));
        self::assertSame([World::COORDINATOR, World::APPROVER], $this->world->notifier->recipientsOf(NotificationType::DecisionRecalled->value));

        try {
            $this->world->get(SendDecisionForApproval::class)->execute(
                new SendDecisionCommand(self::DECISION, World::COORDINATOR, World::APPROVER, base64_encode('cms')),
            );
            self::fail('После отзыва нужен новый сводный документ.');
        } catch (ValidationFailed $error) {
            self::assertSame('complaints_error_consolidated_missing', $error->messageKey());
        }

        $this->steps->send(self::DECISION);
        self::assertSame(2, $this->world->documents->current(DocumentType::ConsolidatedAnswer, self::DECISION)?->version);
        self::assertSame(BuyComplaintStatus::OnCoordination, $this->world->decisions->get(self::DECISION)->status);
        self::assertSame([SignatureStatus::Signed, SignatureStatus::Pending, SignatureStatus::Pending], $this->consolidatedSignatureStatuses());
    }

    public function testRecalledDecisionReopensForChanges(): void
    {
        $this->answerEverything();
        $this->steps->send(self::DECISION);
        $this->world->get(RecallDecision::class)->execute(self::DECISION);

        $this->world->get(ReopenDecision::class)->execute(self::DECISION);
        $this->world->get(ResetAnswerDocument::class)->execute((int) $this->steps->answerOf($this->third));
        $this->steps->answer([$this->third => Verdict::Satisfied], DecisionType::CancelPurchase);

        self::assertSame(BuyComplaintStatus::InReview, $this->world->decisions->get(self::DECISION)->status);
        self::assertNull($this->world->documents->current(DocumentType::ConsolidatedAnswer, self::DECISION));
        self::assertSame(DecisionType::CancelPurchase, $this->world->decisions->lot($this->steps->decisionLotOf($this->third))->decisionType);
    }

    public function testApprovedDecisionCannotBeRecalled(): void
    {
        $this->steps->decide(
            [$this->first => Verdict::Rejected, $this->second => Verdict::Rejected, $this->third => Verdict::Rejected],
        );
        self::assertSame(ExecutionStatus::NotRequired, $this->world->decisions->get(self::DECISION)->executionStatus);
        self::assertSame([World::ORGANIZER_ORG], $this->world->notifier->recipientsOf(NotificationType::DecisionWithoutExecution->value, 'organization'));

        $this->world->actAs(World::REVIEWER);
        $this->expectException(StatusConflict::class);
        $this->world->get(RecallDecision::class)->execute(self::DECISION);
    }

    public function testNextAppealPeriodOfTheBuyGetsItsOwnDecision(): void
    {
        $this->steps->decide(
            [$this->first => Verdict::Satisfied, $this->second => Verdict::Rejected, $this->third => Verdict::Rejected],
            [World::LOT_A => DecisionType::ReviseResults],
        );

        // После пересмотра итогов опубликован новый протокол — проект установил новый срок обжалования.
        $this->world->procurement->setAppealPeriod(World::BUY, Period::ofDays('2026-10-27', '2026-10-29', new DateTimeZone('Asia/Almaty')));
        $this->world->at('2026-10-27 10:00:00+05:00');
        $again = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $this->world->at('2026-10-30 00:10:00+05:00');
        $this->world->get(CollectSubmittedComplaints::class)->execute();

        $next = $this->steps->decisionOf($again);
        self::assertNotSame(self::DECISION, $next);
        $this->steps->decide([$again => Verdict::Rejected]);

        self::assertSame(BuyComplaintStatus::Approved, $this->world->decisions->get($next)->status);
        self::assertSame('2026-10-29 23:59', $this->world->decisions->get($next)->appealPeriod->displayEndsAt()->format('Y-m-d H:i'));
        self::assertSame('2026-10-19 23:59', $this->world->complaints->get($this->first)->appealPeriod->displayEndsAt()->format('Y-m-d H:i'), 'Собранная жалоба хранит срок своего решения.');
    }
}
