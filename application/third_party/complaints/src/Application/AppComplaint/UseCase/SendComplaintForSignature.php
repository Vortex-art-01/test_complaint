<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\Access\Actor;
use Complaints\Application\AppComplaint\ComplaintContentRules;
use Complaints\Application\AppComplaint\ComplaintPdf;
use Complaints\Application\AppComplaint\ComplaintRequisites;
use Complaints\Application\AppComplaint\DraftAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\ApplicationEligibility;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\AppComplaint\AppComplaintStatus;

/**
 * «Далее» на форме: draft -> awaiting_signature. Фиксирует реквизиты,
 * формирует PDF, создаёт ожидающую подпись руководителя и уведомляет его.
 */
final class SendComplaintForSignature
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DraftAccess $access,
        private readonly ComplaintContentRules $content,
        private readonly ApplicationEligibility $eligibility,
        private readonly AppComplaintRepository $complaints,
        private readonly ComplaintRequisites $requisites,
        private readonly ComplaintPdf $pdf,
        private readonly StatusTransitioner $transitions,
        private readonly Notifier $notifier,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws ValidationFailed поля не заполнены, срок закрыт или заявка утратила право на жалобу
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $complaintId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $complaintId, $now): void {
            $complaint = $this->access->lockForAuthor($user, $complaintId, [AppComplaintStatus::Draft], $now);
            $this->content->check($complaint->contactPhone, $complaint->description, true);

            $lot = $this->complaints->lotOf($complaintId);
            if (!$this->eligibility->isEligible($complaint->appId, $lot->appLotId)) {
                throw ValidationFailed::field('form', 'complaints_error_lot_not_complainable');
            }

            $requisites = $this->requisites->current($complaint, $lot);
            $this->pdf->issue($complaint, $requisites, Actor::of($user), $now);
            $this->transitions->moveComplaint($complaint, AppComplaintStatus::AwaitingSignature, $now);

            $this->notifier->notifyUser($complaint->headUserId, new Notification(
                NotificationType::ComplaintAwaitingSignature,
                [
                    'number' => $complaint->number->value,
                    'buy' => $requisites->buyNumber,
                    'lot' => $requisites->lotNumber,
                    'author' => $requisites->representativeName,
                ],
                NotificationTarget::Complaint,
                $complaintId,
            ));
        });
    }
}
