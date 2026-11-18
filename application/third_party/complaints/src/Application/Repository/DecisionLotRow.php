<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Shared\LocalizedText;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;

/**
 * Лот решения со сводкой по жалобам: покрытие ответами и вердикты.
 */
final readonly class DecisionLotRow
{
    /**
     * @param int $answeredCount лоты жалоб с ответом, по которому сформирован действующий PDF
     */
    public function __construct(
        public BuyComplaintLot $lot,
        public string $buyLotNumber,
        public LocalizedText $buyLotName,
        public int $complaintsCount,
        public int $answeredCount,
        public int $satisfiedCount,
        public int $rejectedCount,
    ) {
    }

    public function isCovered(): bool
    {
        return $this->answeredCount === $this->complaintsCount
            && $this->satisfiedCount + $this->rejectedCount === $this->complaintsCount;
    }
}
