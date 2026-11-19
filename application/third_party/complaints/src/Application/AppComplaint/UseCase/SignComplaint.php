<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\Actor;
use Complaints\Application\AppComplaint\DraftAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\SignatureRejected;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\SignatureService;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Signature\SignerRole;

/**
 * Подпись жалобы руководителем: awaiting_signature -> signed.
 * Подпись проверяется на сервере над байтами действующей версии PDF.
 */
final class SignComplaint
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly DraftAccess $access,
        private readonly AppComplaintRepository $complaints,
        private readonly DocumentRepository $documents,
        private readonly OrganizationDirectory $directory,
        private readonly SignatureService $signatures,
        private readonly StatusTransitioner $transitions,
        private readonly Notifier $notifier,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param string $cmsBase64 отсоединённая CMS-подпись PDF в base64
     *
     * @throws SignatureRejected
     * @throws ValidationFailed срок обжалования закрыт
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $complaintId, string $cmsBase64): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $complaintId, $cmsBase64, $now): void {
            $complaint = $this->complaints->lock($complaintId);
            $this->guard->assertAllowed($this->guard->isComplaintHead($user, $complaint));
            $this->access->assertStatus($complaint, [AppComplaintStatus::AwaitingSignature]);
            $this->access->assertAppealOpen($complaint, $now);

            $document = $this->documents->current(DocumentType::AppComplaint, $complaintId)
                ?? throw new StatusConflict();
            $supplier = $this->directory->organization($complaint->supplierOrgId);
            $this->signatures->sign($document, SignerRole::Head, Actor::of($user), $cmsBase64, $supplier->bin, $now);
            $this->transitions->moveComplaint($complaint, AppComplaintStatus::Signed, $now);

            $this->notifier->notifyUser($complaint->authorUserId, new Notification(
                NotificationType::ComplaintSigned,
                ['number' => $complaint->number->value],
                NotificationTarget::Complaint,
                $complaintId,
            ));
        });
    }
}
