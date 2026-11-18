<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\AppComplaint\DraftAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Application\Repository\TransactionManager;
use Complaints\Application\Service\AttachmentRules;
use Complaints\Application\Shared\UploadedFile;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\Document\AttachmentOwner;

/**
 * «Добавить файл» на форме черновика.
 */
final class AddComplaintAttachment
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DraftAccess $access,
        private readonly AttachmentRules $rules,
        private readonly AttachmentRepository $attachments,
        private readonly FileStorage $files,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return int id вложения
     *
     * @throws ValidationFailed
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $complaintId, UploadedFile $file): int
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use ($user, $complaintId, $file, $now): int {
            $this->access->lockForAuthor($user, $complaintId, [AppComplaintStatus::Draft], $now);
            $this->rules->assertAcceptable($file, count($this->attachments->of(AttachmentOwner::AppComplaint, $complaintId)));

            $fileId = $this->files->store($file->bytes, $file->fileName, $file->mimeType, $user->userId);

            return $this->attachments->add(AttachmentOwner::AppComplaint, $complaintId, $fileId, $user->userId, $now);
        });
    }
}
