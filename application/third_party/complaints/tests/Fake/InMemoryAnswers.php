<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Domain\BuyComplaint\Answer;
use DateTimeImmutable;

final class InMemoryAnswers implements AnswerRepository
{
    /** @var array<int, array<string, mixed>> */
    public array $answers = [];

    public function __construct(
        private readonly InMemoryBuyComplaints $decisions,
    ) {
    }

    public function get(int $answerId): Answer
    {
        $row = $this->answers[$answerId] ?? null;
        if ($row === null || $row['deleted_at'] !== null) {
            throw new NotFound();
        }

        return new Answer($row['id'], $row['lot_id'], $row['text'], $row['created_by'], $row['created_at'], null);
    }

    public function create(int $buyComplaintLotId, string $text, int $userId, DateTimeImmutable $now): int
    {
        $id = count($this->answers) + 1;
        $this->answers[$id] = [
            'id' => $id,
            'lot_id' => $buyComplaintLotId,
            'text' => $text,
            'created_by' => $userId,
            'created_at' => $now,
            'deleted_at' => null,
        ];

        return $id;
    }

    public function updateText(int $answerId, string $text, DateTimeImmutable $now): void
    {
        $this->answers[$answerId]['text'] = $text;
    }

    public function softDelete(int $answerId, DateTimeImmutable $now): void
    {
        $this->answers[$answerId]['deleted_at'] = $now;
    }

    public function softDeleteOfBuyComplaint(int $buyComplaintId, DateTimeImmutable $now): array
    {
        $lotIds = array_map(static fn ($lot): int => $lot->id, $this->decisions->lots($buyComplaintId));
        $deleted = [];
        foreach ($this->answers as $id => $row) {
            if ($row['deleted_at'] === null && in_array($row['lot_id'], $lotIds, true)) {
                $this->answers[$id]['deleted_at'] = $now;
                $deleted[] = $id;
            }
        }

        return $deleted;
    }

    public function ofBuyComplaintLot(int $buyComplaintLotId): array
    {
        $found = [];
        foreach ($this->answers as $row) {
            if ($row['deleted_at'] === null && $row['lot_id'] === $buyComplaintLotId) {
                $found[] = $this->get($row['id']);
            }
        }

        return $found;
    }
}
