<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Repository\SignatureRepository;
use Complaints\Application\Repository\TransactionManager;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\Document\DocumentType;

/**
 * «Отозвать» до подписи утверждающего: on_coordination | on_approval -> recalled.
 * Аннулируются все подписи версии, включая подпись рассматривающего;
 * ответы и решения сохраняются. Повторная отправка — новая версия документа.
 */
final class RecallDecision
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly DecisionAccess $access,
        private readonly BuyComplaintRepository $decisions,
        private readonly DocumentRepository $documents,
        private readonly SignatureRepository $signatures,
        private readonly StatusTransitioner $transitions,
        private readonly ProcurementRepository $procurement,
        private readonly Notifier $notifier,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws AccessDenied
     * @throws StatusConflict утверждающий уже подписал
     */
    public function execute(int $decisionId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $decisionId, $now): void {
            $decision = $this->decisions->lock($decisionId);
            $this->guard->assertAllowed($this->guard->canReview($user, $decision) && $decision->isReviewedBy($user->userId));
            $this->access->assertStatus($decision, [BuyComplaintStatus::OnCoordination, BuyComplaintStatus::OnApproval]);

            $document = $this->documents->current(DocumentType::ConsolidatedAnswer, $decisionId)
                ?? throw new StatusConflict();
            $this->signatures->annulAll($document->id, $user->userId, $now);
            $this->transitions->moveDecision($decision, BuyComplaintStatus::Recalled, $user->userId, $now);

            $notification = new Notification(
                NotificationType::DecisionRecalled,
                ['buy' => $this->procurement->buy($decision->buyId)->number],
                NotificationTarget::Decision,
                $decisionId,
            );
            foreach (array_filter([$decision->coordinatorUserId, $decision->approverUserId]) as $memberId) {
                $this->notifier->notifyUser($memberId, $notification);
            }
        });
    }
}
