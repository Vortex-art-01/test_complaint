<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\SignatureRejected;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyCard;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Repository\TransactionManager;
use Complaints\Application\Service\DeadlineCalculator;
use Complaints\Application\Service\SignatureService;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Application\Shared\DisplayDate;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionNumber;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Signature\SignerRole;
use DateTimeImmutable;

/**
 * «Утвердить и подписать»: on_approval -> approved.
 *
 * Присваивает номер и дату решения; жалобы переходят в reviewed; если все
 * лоты «без изменений» — исполнение не требуется, иначе исполнение со сроком
 * и лоты с отменой или пересмотром ждут исполнения. Уведомляет поставщиков,
 * организатора и рассматривающего.
 */
final class ApproveDecision
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly DecisionAccess $access,
        private readonly BuyComplaintRepository $decisions,
        private readonly AppComplaintRepository $complaints,
        private readonly DocumentRepository $documents,
        private readonly OrganizationDirectory $directory,
        private readonly SignatureService $signatures,
        private readonly StatusTransitioner $transitions,
        private readonly ProcurementRepository $procurement,
        private readonly DeadlineCalculator $deadlines,
        private readonly Notifier $notifier,
        private readonly DisplayDate $dates,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws SignatureRejected
     * @throws AccessDenied
     * @throws StatusConflict согласующий не подписал или по лоту нет типа решения
     */
    public function execute(int $decisionId, string $signatureBase64): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $decisionId, $signatureBase64, $now): void {
            $decision = $this->decisions->lock($decisionId);
            $this->guard->assertAllowed($this->guard->canApprove($user, $decision));
            $this->access->assertStatus($decision, [BuyComplaintStatus::OnApproval]);

            $document = $this->documents->current(DocumentType::ConsolidatedAnswer, $decisionId)
                ?? throw new StatusConflict();
            $authority = $this->directory->organization($decision->authorityOrgId);
            $this->signatures->sign($document, SignerRole::Approver, $user->userId, $signatureBase64, $authority->bin, $now);

            $lots = $this->decisions->lots($decisionId);
            $types = [];
            foreach ($lots as $lot) {
                $types[] = $lot->decisionType ?? throw new StatusConflict('complaints_error_decision_incomplete', ['lots' => '']);
            }
            $buy = $this->procurement->buy($decision->buyId);
            $execution = ExecutionStatus::afterApproval($types);

            $this->decisions->lockBuy($decision->buyId);
            $changes = [
                'decision_number' => DecisionNumber::forCycle($buy->number, $this->decisions->countNumberedForBuy($decision->buyId) + 1)->value,
                'approved_at' => $now,
                'execution_status' => $execution,
            ];
            if ($execution === ExecutionStatus::InProgress) {
                $changes['execution_period'] = $this->deadlines->executionPeriod($now);
            }
            $this->transitions->moveDecision($decision, BuyComplaintStatus::Approved, $user->userId, $now, $changes);

            $this->startLotExecution($lots, $user->userId, $now);
            $this->completeComplaints($decisionId, $user->userId, $now);
            $this->notify($this->decisions->get($decisionId), $buy);
        });
    }

    /**
     * @param list<BuyComplaintLot> $lots
     */
    private function startLotExecution(array $lots, int $userId, DateTimeImmutable $now): void
    {
        foreach ($lots as $lot) {
            if ($lot->decisionType !== null && LotExecutionStatus::afterApproval($lot->decisionType) !== null) {
                $this->transitions->moveLotExecution($lot, LotExecutionStatus::Pending, $userId, $now);
            }
        }
    }

    private function completeComplaints(int $decisionId, int $userId, DateTimeImmutable $now): void
    {
        foreach ($this->complaints->ofBuyComplaint($decisionId) as $complaint) {
            if ($complaint->status === AppComplaintStatus::InReview) {
                $this->transitions->moveComplaint($complaint, AppComplaintStatus::Reviewed, $userId, $now);
            }
        }
        $this->complaints->markReviewed($decisionId, $now);
    }

    private function notify(BuyComplaint $decision, BuyCard $buy): void
    {
        foreach ($this->complaints->ofBuyComplaint($decision->id) as $complaint) {
            $this->notifier->notifyOrganization($complaint->supplierOrgId, new Notification(
                NotificationType::ComplaintAnswered,
                ['number' => $complaint->number->value],
                NotificationTarget::Complaint,
                $complaint->id,
            ));
        }

        $requiresExecution = $decision->executionStatus === ExecutionStatus::InProgress;
        $this->notifier->notifyOrganization($buy->organizerOrgId, new Notification(
            $requiresExecution ? NotificationType::DecisionRequiresExecution : NotificationType::DecisionWithoutExecution,
            [
                'buy' => $buy->number,
                'decision' => (string) $decision->decisionNumber,
                'date' => $decision->executionPeriod === null ? '' : $this->dates->moment($decision->executionPeriod->displayEndsAt()),
            ],
            NotificationTarget::Execution,
            $decision->id,
        ));

        if ($decision->reviewerUserId !== null) {
            $this->notifier->notifyUser($decision->reviewerUserId, new Notification(
                NotificationType::DecisionApproved,
                ['buy' => $buy->number, 'decision' => (string) $decision->decisionNumber],
                NotificationTarget::Decision,
                $decision->id,
            ));
        }
    }
}
