<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\Actor;
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
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\DeadlineCalculator;
use Complaints\Application\Service\SignatureService;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Application\Shared\DisplayDate;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Signature\SignerRole;
use DateTimeImmutable;

/**
 * «Утвердить и подписать»: on_approval -> approved.
 *
 * Фиксирует дату утверждения (номер решения — его id); жалобы переходят в reviewed; если все
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
        private readonly TransactionRunner $transactions,
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
            $this->signatures->sign($document, SignerRole::Approver, Actor::of($user), $signatureBase64, $authority->bin, $now);

            $lots = $this->decisions->lots($decisionId);
            $types = [];
            foreach ($lots as $lot) {
                $types[] = $lot->decisionType ?? throw new StatusConflict('complaints_error_decision_incomplete', ['lots' => '']);
            }
            $buy = $this->procurement->buy($decision->buyId);
            $execution = ExecutionStatus::afterApproval($types);

            $changes = [
                'approved_at' => $now,
                'execution_status' => $execution,
            ];
            if ($execution === ExecutionStatus::InProgress) {
                $changes['execution_period'] = $this->deadlines->executionPeriod($now);
            }
            $this->transitions->moveDecision($decision, BuyComplaintStatus::Approved, $now, $changes);

            $this->startLotExecution($lots, $now);
            $this->completeComplaints($decisionId, $now);
            $this->notify($this->decisions->get($decisionId), $buy);
        });
    }

    /**
     * @param list<BuyComplaintLot> $lots
     */
    private function startLotExecution(array $lots, DateTimeImmutable $now): void
    {
        foreach ($lots as $lot) {
            if ($lot->decisionType !== null && LotExecutionStatus::afterApproval($lot->decisionType) !== null) {
                $this->transitions->moveLotExecution($lot, LotExecutionStatus::Pending, $now, null);
            }
        }
    }

    private function completeComplaints(int $decisionId, DateTimeImmutable $now): void
    {
        foreach ($this->complaints->ofBuyComplaint($decisionId) as $complaint) {
            if ($complaint->status === AppComplaintStatus::InReview) {
                $this->transitions->moveComplaint($complaint, AppComplaintStatus::Reviewed, $now);
            }
        }
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
                'decision' => (string) $decision->id,
                'date' => $decision->executionPeriod === null ? '' : $this->dates->moment($decision->executionPeriod->displayEndsAt()),
            ],
            NotificationTarget::Execution,
            $decision->id,
        ));

        if ($decision->reviewerUserId !== null) {
            $this->notifier->notifyUser($decision->reviewerUserId, new Notification(
                NotificationType::DecisionApproved,
                ['buy' => $buy->number, 'decision' => (string) $decision->id],
                NotificationTarget::Decision,
                $decision->id,
            ));
        }
    }
}
