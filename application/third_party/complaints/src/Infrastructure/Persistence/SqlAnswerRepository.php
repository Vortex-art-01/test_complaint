<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Domain\BuyComplaint\Answer;
use DateTimeImmutable;

/**
 * Ответы уполномоченного органа: app_complaint_answer.
 */
final class SqlAnswerRepository implements AnswerRepository
{
    private const COLUMNS = 'a.id, a.trd_complaint_lots_id, a.answer, a.created_at';

    public function __construct(
        private readonly Database $db,
        private readonly SqlValues $values,
    ) {
    }

    public function get(int $answerId): Answer
    {
        $row = $this->db->row('SELECT ' . self::COLUMNS . ' FROM app_complaint_answer a WHERE a.id = :id', ['id' => $answerId]);

        return $row === null ? throw new NotFound() : $this->answer($row);
    }

    public function create(int $buyComplaintLotId, string $text, DateTimeImmutable $now): int
    {
        return $this->values->int($this->db->value(
            'INSERT INTO app_complaint_answer (trd_complaint_lots_id, answer, created_at) VALUES (:lot, :text, :created) RETURNING id',
            ['lot' => $buyComplaintLotId, 'text' => $text, 'created' => $now],
        ));
    }

    public function updateText(int $answerId, string $text, DateTimeImmutable $now): void
    {
        $this->db->execute('UPDATE app_complaint_answer SET answer = :text WHERE id = :id', ['text' => $text, 'id' => $answerId]);
    }

    public function ofBuyComplaint(int $buyComplaintId): array
    {
        return $this->many(
            'JOIN trd_complaint_lots tl ON tl.id = a.trd_complaint_lots_id WHERE tl.trd_complaint_id = :decision ORDER BY a.id',
            ['decision' => $buyComplaintId],
        );
    }

    public function deleteOfBuyComplaint(int $buyComplaintId): void
    {
        $this->db->execute(
            'DELETE FROM app_complaint_answer a USING trd_complaint_lots tl'
            . ' WHERE tl.id = a.trd_complaint_lots_id AND tl.trd_complaint_id = :decision',
            ['decision' => $buyComplaintId],
        );
    }

    public function ofBuyComplaintLot(int $buyComplaintLotId): array
    {
        return $this->many('WHERE a.trd_complaint_lots_id = :lot ORDER BY a.id', ['lot' => $buyComplaintLotId]);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<Answer>
     */
    private function many(string $condition, array $params): array
    {
        return array_map(
            fn (array $row): Answer => $this->answer($row),
            $this->db->rows('SELECT ' . self::COLUMNS . ' FROM app_complaint_answer a ' . $condition, $params),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function answer(array $row): Answer
    {
        return new Answer(
            $this->values->int($row['id']),
            $this->values->int($row['trd_complaint_lots_id']),
            (string) $row['answer'],
            $this->values->moment($row['created_at']),
        );
    }
}
