<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Domain\BuyComplaint\Answer;
use DateTimeImmutable;

final class SqlAnswerRepository implements AnswerRepository
{
    private const COLUMNS = 'a.id, a.test_buy_complaint_lots_id, a.answer_text, a.created_by_user_id, a.created_at, a.deleted_at';

    public function __construct(
        private readonly Database $db,
        private readonly SqlValues $values,
    ) {
    }

    public function get(int $answerId): Answer
    {
        $row = $this->db->row(
            'SELECT ' . self::COLUMNS . ' FROM test_complaint_answers a WHERE a.id = :id AND a.deleted_at IS NULL',
            ['id' => $answerId],
        );

        return $row === null ? throw new NotFound() : $this->answer($row);
    }

    public function create(int $buyComplaintLotId, string $text, int $userId, DateTimeImmutable $now): int
    {
        return $this->values->int($this->db->value(
            'INSERT INTO test_complaint_answers (test_buy_complaint_lots_id, answer_text, created_by_user_id, created_at, updated_at)'
            . ' VALUES (:lot, :text, :user, :created, :updated) RETURNING id',
            ['lot' => $buyComplaintLotId, 'text' => $text, 'user' => $userId, 'created' => $now, 'updated' => $now],
        ));
    }

    public function updateText(int $answerId, string $text, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_complaint_answers SET answer_text = :text, updated_at = :now WHERE id = :id AND deleted_at IS NULL',
            ['text' => $text, 'now' => $now, 'id' => $answerId],
        );
    }

    public function softDelete(int $answerId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_complaint_answers SET deleted_at = :deleted, updated_at = :updated WHERE id = :id AND deleted_at IS NULL',
            ['deleted' => $now, 'updated' => $now, 'id' => $answerId],
        );
    }

    public function softDeleteOfBuyComplaint(int $buyComplaintId, DateTimeImmutable $now): array
    {
        $rows = $this->db->rows(
            'UPDATE test_complaint_answers a SET deleted_at = :deleted, updated_at = :updated'
            . ' FROM test_buy_complaint_lots bl'
            . ' WHERE bl.id = a.test_buy_complaint_lots_id AND bl.test_buy_complaint_id = :decision AND a.deleted_at IS NULL'
            . ' RETURNING a.id',
            ['deleted' => $now, 'updated' => $now, 'decision' => $buyComplaintId],
        );

        return array_map(fn (array $row): int => $this->values->int($row['id']), $rows);
    }

    public function ofBuyComplaintLot(int $buyComplaintLotId): array
    {
        return array_map(
            fn (array $row): Answer => $this->answer($row),
            $this->db->rows(
                'SELECT ' . self::COLUMNS . ' FROM test_complaint_answers a'
                . ' WHERE a.test_buy_complaint_lots_id = :lot AND a.deleted_at IS NULL ORDER BY a.id',
                ['lot' => $buyComplaintLotId],
            ),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function answer(array $row): Answer
    {
        return new Answer(
            $this->values->int($row['id']),
            $this->values->int($row['test_buy_complaint_lots_id']),
            (string) $row['answer_text'],
            $this->values->int($row['created_by_user_id']),
            $this->values->moment($row['created_at']),
            $this->values->momentOrNull($row['deleted_at']),
        );
    }
}
