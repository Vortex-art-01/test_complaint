<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

/**
 * Незаполненный лот решения (ФЛК перед формированием сводного документа).
 */
final readonly class DecisionGap
{
    public function __construct(
        public string $buyLotNumber,
        public int $covered,
        public int $total,
        public bool $missingDecisionType,
        public bool $decisionTypeMismatch,
    ) {
    }
}
