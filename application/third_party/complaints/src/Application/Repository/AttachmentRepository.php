<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Access\Actor;
use Complaints\Application\Exception\NotFound;
use Complaints\Domain\Document\Attachment;
use Complaints\Domain\Document\AttachmentOwner;
use DateTimeImmutable;

interface AttachmentRepository
{
    /**
     * Неудалённые вложения владельца.
     *
     * @return list<Attachment>
     */
    public function of(AttachmentOwner $owner, int $ownerId): array;

    /**
     * Вложение по id (id файла).
     *
     * @throws NotFound вложения нет или оно удалено
     */
    public function get(int $attachmentId): Attachment;

    public function add(AttachmentOwner $owner, int $ownerId, int $fileId, Actor $uploadedBy, DateTimeImmutable $now): int;

    public function remove(int $attachmentId, DateTimeImmutable $now): void;
}
