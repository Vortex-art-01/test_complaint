<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Exception\NotFound;
use Complaints\Domain\Document\Attachment;
use Complaints\Domain\Document\AttachmentOwner;
use DateTimeImmutable;

/**
 * Вложения к жалобам и ответам: test_complaint_attachments.
 */
interface AttachmentRepository
{
    /**
     * Неудалённые вложения владельца.
     *
     * @return list<Attachment>
     */
    public function of(AttachmentOwner $owner, int $ownerId): array;

    /**
     * @throws NotFound вложения нет или оно удалено
     */
    public function get(int $attachmentId): Attachment;

    public function add(AttachmentOwner $owner, int $ownerId, int $fileId, int $userId, DateTimeImmutable $now): int;

    public function remove(int $attachmentId, DateTimeImmutable $now): void;
}
