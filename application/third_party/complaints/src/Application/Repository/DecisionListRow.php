<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Shared\LocalizedText;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\DecisionType;

/**
 * Строка реестра решений: уполномоченного органа или организатора.
 */
final readonly class DecisionListRow
{
    /**
     * @param list<DecisionType> $decisionTypes различные типы решений по лотам
     */
    public function __construct(
        public BuyComplaint $decision,
        public string $buyNumber,
        public LocalizedText $buyName,
        public int $organizerOrgId,
        public int $complaintsCount,
        public array $decisionTypes,
    ) {
    }
}
