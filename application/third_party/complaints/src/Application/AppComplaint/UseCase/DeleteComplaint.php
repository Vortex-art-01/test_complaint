<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Domain\Document\DocumentType;

/**
 * «Удалить»: мягкое удаление жалобы до подачи. Ожидающая подпись
 * руководителя аннулируется, чтобы не висела у него в списке.
 */
final class DeleteComplaint
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly AppComplaintRepository $complaints,
        private readonly DocumentIssuer $documents,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws AccessDenied
     * @throws StatusConflict жалоба уже подана
     */
    public function execute(int $complaintId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $complaintId, $now): void {
            $complaint = $this->complaints->lock($complaintId);
            $this->guard->assertAllowed($this->guard->isComplaintAuthor($user, $complaint));
            if (!$complaint->status->isDeletable()) {
                throw new StatusConflict();
            }

            $this->documents->retire(DocumentType::AppComplaint, $complaintId, $user->userId, $now);
            if (!$this->complaints->softDelete($complaintId, $now)) {
                throw new StatusConflict();
            }
        });
    }
}
