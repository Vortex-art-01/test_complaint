<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Domain\Document\Attachment;
use Complaints\Domain\Document\AttachmentOwner;
use DateTimeImmutable;

final class SqlAttachmentRepository implements AttachmentRepository
{
    private const COLUMNS = 'id, test_app_complaint_id, test_complaint_answers_id, file_id, uploaded_by_user_id, created_at, deleted_at';

    public function __construct(
        private readonly Database $db,
        private readonly SqlValues $values,
    ) {
    }

    public function of(AttachmentOwner $owner, int $ownerId): array
    {
        return array_map(
            fn (array $row): Attachment => $this->attachment($row),
            $this->db->rows(
                sprintf('SELECT %s FROM test_complaint_attachments WHERE %s = :owner AND deleted_at IS NULL ORDER BY id', self::COLUMNS, self::ownerColumn($owner)),
                ['owner' => $ownerId],
            ),
        );
    }

    public function get(int $attachmentId): Attachment
    {
        $row = $this->db->row(
            'SELECT ' . self::COLUMNS . ' FROM test_complaint_attachments WHERE id = :id AND deleted_at IS NULL',
            ['id' => $attachmentId],
        );

        return $row === null ? throw new NotFound() : $this->attachment($row);
    }

    public function add(AttachmentOwner $owner, int $ownerId, int $fileId, int $userId, DateTimeImmutable $now): int
    {
        return $this->values->int($this->db->value(
            sprintf(
                'INSERT INTO test_complaint_attachments (%s, file_id, uploaded_by_user_id, created_at) VALUES (:owner, :file, :user, :now) RETURNING id',
                self::ownerColumn($owner),
            ),
            ['owner' => $ownerId, 'file' => $fileId, 'user' => $userId, 'now' => $now],
        ));
    }

    public function remove(int $attachmentId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_complaint_attachments SET deleted_at = :now WHERE id = :id AND deleted_at IS NULL',
            ['now' => $now, 'id' => $attachmentId],
        );
    }

    private static function ownerColumn(AttachmentOwner $owner): string
    {
        return match ($owner) {
            AttachmentOwner::AppComplaint => 'test_app_complaint_id',
            AttachmentOwner::Answer => 'test_complaint_answers_id',
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function attachment(array $row): Attachment
    {
        $owner = $row['test_app_complaint_id'] !== null ? AttachmentOwner::AppComplaint : AttachmentOwner::Answer;

        return new Attachment(
            $this->values->int($row['id']),
            $owner,
            $this->values->int($row[self::ownerColumn($owner)]),
            $this->values->int($row['file_id']),
            $this->values->int($row['uploaded_by_user_id']),
            $this->values->moment($row['created_at']),
            $this->values->momentOrNull($row['deleted_at']),
        );
    }
}
