<?php

declare(strict_types=1);

namespace Complaints\Application\File\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\File\FileAccess;
use Complaints\Application\File\FileKind;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Port\StoredFile;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\SignatureRepository;

/**
 * Скачивание файла модуля с проверкой прав (а не по ссылке хранилища).
 */
final class DownloadFile
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly FileAccess $access,
        private readonly DocumentRepository $documents,
        private readonly AttachmentRepository $attachments,
        private readonly SignatureRepository $signatures,
        private readonly FileStorage $files,
    ) {
    }

    /**
     * @throws NotFound
     * @throws AccessDenied
     */
    public function execute(FileKind $kind, int $id): StoredFile
    {
        $user = $this->users->currentUser();

        $fileId = match ($kind) {
            FileKind::Document => $this->documentFile($id, $user),
            FileKind::Attachment => $this->attachmentFile($id, $user),
            FileKind::Signature => $this->signatureFile($id, $user),
        };

        return $this->files->read($fileId);
    }

    private function documentFile(int $documentId, CurrentUser $user): int
    {
        $document = $this->documents->get($documentId);
        $this->guard->assertAllowed($this->access->canReadDocument($user, $document));

        return $document->fileId;
    }

    private function attachmentFile(int $attachmentId, CurrentUser $user): int
    {
        $attachment = $this->attachments->get($attachmentId);
        $this->guard->assertAllowed($this->access->canReadAttachment($user, $attachment));

        return $attachment->fileId;
    }

    private function signatureFile(int $signatureId, CurrentUser $user): int
    {
        $signature = $this->signatures->get($signatureId);
        $this->guard->assertAllowed($this->access->canReadSignatures($user, $this->documents->get($signature->documentId)));

        return $signature->signatureFileId ?? throw new NotFound();
    }
}
