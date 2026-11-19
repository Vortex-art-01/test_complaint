<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Exception\NotFound;
use Complaints\Domain\BuyComplaint\Answer;
use DateTimeImmutable;

interface AnswerRepository
{
    /**
     * @throws NotFound
     */
    public function get(int $answerId): Answer;

    public function create(int $buyComplaintLotId, string $text, DateTimeImmutable $now): int;

    public function updateText(int $answerId, string $text, DateTimeImmutable $now): void;

    /**
     * Ответы всех лотов решения.
     *
     * @return list<Answer>
     */
    public function ofBuyComplaint(int $buyComplaintId): array;

    /**
     * Удаляет ответы решения — при смене способа ответа. Ссылки лотов жалоб
     * на ответы нужно снять до вызова.
     */
    public function deleteOfBuyComplaint(int $buyComplaintId): void;

    /**
     * @return list<Answer>
     */
    public function ofBuyComplaintLot(int $buyComplaintLotId): array;
}
