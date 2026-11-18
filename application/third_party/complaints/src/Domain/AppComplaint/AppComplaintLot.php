<?php

declare(strict_types=1);

namespace Complaints\Domain\AppComplaint;

use Complaints\Domain\BuyComplaint\Verdict;
use DateTimeImmutable;

/**
 * Лот жалобы: лот заявки поставщика, вердикт и ссылка на ответ.
 */
final readonly class AppComplaintLot
{
    public function __construct(
        public int $id,
        public int $complaintId,
        public int $appId,
        public int $appLotId,
        public int $buyLotId,
        public ?int $buyComplaintLotId,
        public ?Verdict $verdict,
        public ?int $answerId,
        public ?DateTimeImmutable $reviewedAt,
        public ?DateTimeImmutable $deletedAt,
    ) {
    }

    public function hasVerdict(): bool
    {
        return $this->verdict !== null;
    }

    public function hasAnswer(): bool
    {
        return $this->answerId !== null;
    }
}
