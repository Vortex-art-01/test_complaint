<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Access\ModuleRole;
use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\BuyComplaint\DecisionCompleteness;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\SignatureRejected;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationErrors;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\AuthorityResolver;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Repository\SignatureRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\SignatureService;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Signature\SignerRole;

/**
 * Подпись сводного документа рассматривающим и отправка по маршруту:
 * in_review | recalled -> on_coordination (есть согласующий) | on_approval.
 * После отзыва документ формируется заново: подписи прежней версии аннулированы.
 */
final class SendDecisionForApproval
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DecisionAccess $access,
        private readonly DecisionCompleteness $completeness,
        private readonly AuthorityResolver $authorities,
        private readonly OrganizationDirectory $directory,
        private readonly DocumentRepository $documents,
        private readonly SignatureRepository $signatureRows,
        private readonly SignatureService $signatures,
        private readonly StatusTransitioner $transitions,
        private readonly ProcurementRepository $procurement,
        private readonly Notifier $notifier,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws ValidationFailed маршрут неверен или сводный документ не сформирован
     * @throws SignatureRejected
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(SendDecisionCommand $command): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $command, $now): void {
            $decision = $this->access->lockForReviewer(
                $user,
                $command->decisionId,
                [BuyComplaintStatus::InReview, BuyComplaintStatus::Recalled],
                $now,
            );
            $this->completeness->assertComplete($decision->id);
            $this->assertRoute($decision, $user, $command);

            $document = $this->documents->current(DocumentType::ConsolidatedAnswer, $decision->id)
                ?? throw ValidationFailed::field('form', 'complaints_error_consolidated_missing');
            if ($this->signatureRows->ofDocument($document->id) !== []) {
                throw ValidationFailed::field('form', 'complaints_error_consolidated_outdated');
            }

            $route = [SignerRole::Reviewer->value => $user->userId];
            if ($command->coordinatorUserId !== null) {
                $route[SignerRole::Coordinator->value] = $command->coordinatorUserId;
            }
            $route[SignerRole::Approver->value] = $command->approverUserId;
            $this->signatures->requestRoute($document, $route, $now);

            $authority = $this->directory->organization($decision->authorityOrgId);
            $this->signatures->sign($document, SignerRole::Reviewer, $user->userId, $command->signatureBase64, $authority->bin, $now);

            $hasCoordinator = $command->coordinatorUserId !== null;
            $this->transitions->moveDecision(
                $decision,
                $hasCoordinator ? BuyComplaintStatus::OnCoordination : BuyComplaintStatus::OnApproval,
                $user->userId,
                $now,
                [
                    'coordinator_user_id' => $command->coordinatorUserId,
                    'approver_user_id' => $command->approverUserId,
                    'sent_for_approval_at' => $now,
                ],
            );

            $this->notifier->notifyUser($command->coordinatorUserId ?? $command->approverUserId, new Notification(
                $hasCoordinator ? NotificationType::DecisionForCoordination : NotificationType::DecisionForApproval,
                ['buy' => $this->procurement->buy($decision->buyId)->number],
                NotificationTarget::Decision,
                $decision->id,
            ));
        });
    }

    /**
     * Согласующий и утверждающий — сотрудники органа с этими ролями;
     * все участники маршрута — разные люди.
     *
     * @throws ValidationFailed
     */
    private function assertRoute(BuyComplaint $decision, CurrentUser $user, SendDecisionCommand $command): void
    {
        $errors = new ValidationErrors();
        $approvers = $this->ids($decision->authorityOrgId, ModuleRole::Approver);
        if (!in_array($command->approverUserId, $approvers, true)) {
            $errors->add('approver_user_id', 'complaints_error_route_member');
        }
        if ($command->coordinatorUserId !== null
            && !in_array($command->coordinatorUserId, $this->ids($decision->authorityOrgId, ModuleRole::Coordinator), true)) {
            $errors->add('coordinator_user_id', 'complaints_error_route_member');
        }

        $members = array_filter([$user->userId, $command->coordinatorUserId, $command->approverUserId], static fn ($id): bool => $id !== null);
        if (count($members) !== count(array_unique($members))) {
            $errors->add('approver_user_id', 'complaints_error_route_distinct');
        }

        $errors->throwIfAny();
    }

    /**
     * @return list<int>
     */
    private function ids(int $authorityOrgId, ModuleRole $role): array
    {
        return array_map(static fn ($card): int => $card->id, $this->authorities->employees($authorityOrgId, $role));
    }
}
