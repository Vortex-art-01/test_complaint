<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Access\Actor;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Domain\Document\Attachment;
use Complaints\Domain\Document\AttachmentOwner;
use DateTimeImmutable;

/**
 * Вложения в памяти: id вложения — id файла, как в SQL-репозитории.
 */
final class InMemoryAttachments implements AttachmentRepository
{
    /** @var array<int, Attachment> */
    public array $attachments = [];

    public function of(AttachmentOwner $owner, int $ownerId): array
    {
        return array_values(array_filter(
            $this->attachments,
            static fn (Attachment $a): bool => $a->owner === $owner && $a->ownerId === $ownerId && $a->deletedAt === null,
        ));
    }

    public function get(int $attachmentId): Attachment
    {
        $attachment = $this->attachments[$attachmentId] ?? null;
        if ($attachment === null || $attachment->deletedAt !== null) {
            throw new NotFound();
        }

        return $attachment;
    }

    public function add(AttachmentOwner $owner, int $ownerId, int $fileId, Actor $uploadedBy, DateTimeImmutable $now): int
    {
        $this->attachments[$fileId] = new Attachment($fileId, $owner, $ownerId, $fileId, $uploadedBy->userId, $now, null);

        return $fileId;
    }

    public function remove(int $attachmentId, DateTimeImmutable $now): void
    {
        $a = $this->attachments[$attachmentId];
        $this->attachments[$attachmentId] = new Attachment($a->id, $a->owner, $a->ownerId, $a->fileId, $a->uploadedByUserId, $a->createdAt, $now);
    }
}
