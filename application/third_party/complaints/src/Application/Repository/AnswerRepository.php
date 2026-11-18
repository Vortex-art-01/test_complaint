<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Exception\NotFound;
use Complaints\Domain\BuyComplaint\Answer;
use DateTimeImmutable;

/**
 * Ответы уполномоченного органа: test_complaint_answers.
 */
interface AnswerRepository
{
    /**
     * @throws NotFound ответа нет или он удалён
     */
    public function get(int $answerId): Answer;

    public function create(int $buyComplaintLotId, string $text, int $userId, DateTimeImmutable $now): int;

    public function updateText(int $answerId, string $text, DateTimeImmutable $now): void;

    public function softDelete(int $answerId, DateTimeImmutable $now): void;

    /**
     * Мягко удаляет все ответы решения.
     *
     * @return list<int> id удалённых ответов
     */
    public function softDeleteOfBuyComplaint(int $buyComplaintId, DateTimeImmutable $now): array;

    /**
     * Действующие ответы лота решения.
     *
     * @return list<Answer>
     */
    public function ofBuyComplaintLot(int $buyComplaintLotId): array;
}
