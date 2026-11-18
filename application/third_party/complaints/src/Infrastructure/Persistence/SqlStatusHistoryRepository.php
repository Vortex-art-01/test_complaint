<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Repository\StatusHistoryRepository;
use Complaints\Domain\History\HistoryEntityType;
use DateTimeImmutable;

final class SqlStatusHistoryRepository implements StatusHistoryRepository
{
    public function __construct(
        private readonly Database $db,
    ) {
    }

    public function append(
        HistoryEntityType $type,
        int $entityId,
        ?string $fromStatus,
        string $toStatus,
        ?int $actorUserId,
        DateTimeImmutable $now,
        ?string $comment = null,
    ): void {
        $this->db->execute(
            'INSERT INTO test_complaint_status_history (entity_type, entity_id, from_status, to_status, actor_user_id, comment, created_at)'
            . ' VALUES (:type, :entity, :from, :to, :actor, :comment, :now)',
            [
                'type' => $type,
                'entity' => $entityId,
                'from' => $fromStatus,
                'to' => $toStatus,
                'actor' => $actorUserId,
                'comment' => $comment === null ? null : mb_substr($comment, 0, 2000),
                'now' => $now,
            ],
        );
    }
}
