<?php

declare(strict_types=1);

namespace Complaints\Domain\Document;

use DateTimeImmutable;

/**
 * Файл, приложенный к жалобе или к ответу (снимок строки test_complaint_attachments).
 */
final readonly class Attachment
{
    public function __construct(
        public int $id,
        public AttachmentOwner $owner,
        public int $ownerId,
        public int $fileId,
        public int $uploadedByUserId,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $deletedAt,
    ) {
    }
}
