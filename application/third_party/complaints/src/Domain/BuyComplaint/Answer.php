<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

use DateTimeImmutable;

/**
 * Ответ уполномоченного органа по лоту решения (снимок строки test_complaint_answers).
 *
 * per_complaint — ответ одному лоту жалобы, per_lot — всем жалобам лота закупки.
 */
final readonly class Answer
{
    public const MAX_LENGTH = 20000;

    public function __construct(
        public int $id,
        public int $buyComplaintLotId,
        public string $text,
        public int $createdByUserId,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $deletedAt,
    ) {
    }

    public function hasText(): bool
    {
        return trim($this->text) !== '';
    }
}
