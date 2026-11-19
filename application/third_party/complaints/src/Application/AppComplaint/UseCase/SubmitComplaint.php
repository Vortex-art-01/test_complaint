<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\AppComplaint\DraftAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\SignatureService;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\Document\DocumentType;

/**
 * «Подать жалобу»: signed | withdrawn -> submitted. Срок обжалования
 * проверяется ещё раз в момент подачи; подпись руководителя должна стоять
 * на действующей версии PDF.
 */
final class SubmitComplaint
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly DraftAccess $access,
        private readonly AppComplaintRepository $complaints,
        private readonly DocumentRepository $documents,
        private readonly SignatureService $signatures,
        private readonly StatusTransitioner $transitions,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws ValidationFailed срок обжалования истёк
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $complaintId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $complaintId, $now): void {
            $complaint = $this->complaints->lock($complaintId);
            $this->guard->assertAllowed(
                $this->guard->isComplaintAuthor($user, $complaint) || $this->guard->isComplaintHead($user, $complaint),
            );
            $this->access->assertStatus($complaint, [AppComplaintStatus::Signed, AppComplaintStatus::Withdrawn]);
            $this->access->assertAppealOpen($complaint, $now);

            $document = $this->documents->current(DocumentType::AppComplaint, $complaintId);
            if ($document === null || !$this->signatures->isFullySigned($document)) {
                throw new StatusConflict('complaints_error_not_signed');
            }

            $this->transitions->moveComplaint(
                $complaint,
                AppComplaintStatus::Submitted,
                $now,
                ['submitted_at' => $now, 'submitted_by_user_id' => $user->userId],
            );
        });
    }
}
