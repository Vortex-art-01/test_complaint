<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Repository\StatusHistoryRepository;
use Complaints\Domain\History\HistoryEntityType;
use DateTimeImmutable;

final class InMemoryHistory implements StatusHistoryRepository
{
    /** @var list<array{type: HistoryEntityType, id: int, from: ?string, to: string, actor: ?int}> */
    public array $entries = [];

    public function append(
        HistoryEntityType $type,
        int $entityId,
        ?string $fromStatus,
        string $toStatus,
        ?int $actorUserId,
        DateTimeImmutable $now,
        ?string $comment = null,
    ): void {
        $this->entries[] = ['type' => $type, 'id' => $entityId, 'from' => $fromStatus, 'to' => $toStatus, 'actor' => $actorUserId];
    }

    /**
     * Цепочка статусов сущности: ['draft', 'awaiting_signature', ...].
     *
     * @return list<string>
     */
    public function trail(HistoryEntityType $type, int $entityId): array
    {
        $trail = [];
        foreach ($this->entries as $entry) {
            if ($entry['type'] === $type && $entry['id'] === $entityId) {
                $trail[] = $entry['to'];
            }
        }

        return $trail;
    }
}
