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
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\SignatureService;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Signature\SignerRole;

/**
 * «Согласовать и подписать»: on_coordination -> on_approval; coordinated_at —
 * момент согласования и передачи решения утверждающему.
 */
final class CoordinateDecision
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly DecisionAccess $access,
        private readonly BuyComplaintRepository $decisions,
        private readonly DocumentRepository $documents,
        private readonly OrganizationDirectory $directory,
        private readonly SignatureService $signatures,
        private readonly StatusTransitioner $transitions,
        private readonly ProcurementRepository $procurement,
        private readonly Notifier $notifier,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws SignatureRejected
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $decisionId, string $signatureBase64): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $decisionId, $signatureBase64, $now): void {
            $decision = $this->decisions->lock($decisionId);
            $this->guard->assertAllowed($this->guard->canCoordinate($user, $decision));
            $this->access->assertStatus($decision, [BuyComplaintStatus::OnCoordination]);

            $document = $this->documents->current(DocumentType::ConsolidatedAnswer, $decisionId)
                ?? throw new StatusConflict();
            $authority = $this->directory->organization($decision->authorityOrgId);
            $this->signatures->sign($document, SignerRole::Coordinator, Actor::of($user), $signatureBase64, $authority->bin, $now);
            $this->transitions->moveDecision($decision, BuyComplaintStatus::OnApproval, $now, ['coordinated_at' => $now]);

            $this->notifier->notifyUser((int) $decision->approverUserId, new Notification(
                NotificationType::DecisionForApproval,
                ['buy' => $this->procurement->buy($decision->buyId)->number],
                NotificationTarget::Decision,
                $decisionId,
            ));
        });
    }
}
