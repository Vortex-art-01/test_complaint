<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\AppComplaint\DraftAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Application\Repository\TransactionManager;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\Document\AttachmentOwner;

/**
 * Удаление вложения из черновика (мягкое).
 */
final class RemoveComplaintAttachment
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DraftAccess $access,
        private readonly AttachmentRepository $attachments,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws NotFound
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $complaintId, int $attachmentId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $complaintId, $attachmentId, $now): void {
            $this->access->lockForAuthor($user, $complaintId, [AppComplaintStatus::Draft], $now);
            $attachment = $this->attachments->get($attachmentId);
            if ($attachment->owner !== AttachmentOwner::AppComplaint || $attachment->ownerId !== $complaintId) {
                throw new NotFound();
            }

            $this->attachments->remove($attachmentId, $now);
        });
    }
}
