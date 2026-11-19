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

    private int $nextId = 1;

    public function __construct(
        private readonly InMemoryBuyComplaints $decisions,
    ) {
    }

    public function get(int $answerId): Answer
    {
        $row = $this->answers[$answerId] ?? throw new NotFound();

        return new Answer($row['id'], $row['lot_id'], $row['text'], $row['created_at']);
    }

    public function create(int $buyComplaintLotId, string $text, DateTimeImmutable $now): int
    {
        $id = $this->nextId++;
        $this->answers[$id] = ['id' => $id, 'lot_id' => $buyComplaintLotId, 'text' => $text, 'created_at' => $now];

        return $id;
    }

    public function updateText(int $answerId, string $text, DateTimeImmutable $now): void
    {
        $this->answers[$answerId]['text'] = $text;
    }

    public function ofBuyComplaint(int $buyComplaintId): array
    {
        $lotIds = array_map(static fn ($lot): int => $lot->id, $this->decisions->lots($buyComplaintId));

        return array_values(array_map(
            fn (array $row): Answer => $this->get($row['id']),
            array_filter($this->answers, static fn (array $row): bool => in_array($row['lot_id'], $lotIds, true)),
        ));
    }

    public function deleteOfBuyComplaint(int $buyComplaintId): void
    {
        foreach ($this->ofBuyComplaint($buyComplaintId) as $answer) {
            unset($this->answers[$answer->id]);
        }
    }

    public function ofBuyComplaintLot(int $buyComplaintLotId): array
    {
        return array_values(array_map(
            fn (array $row): Answer => $this->get($row['id']),
            array_filter($this->answers, static fn (array $row): bool => $row['lot_id'] === $buyComplaintLotId),
        ));
    }

}
