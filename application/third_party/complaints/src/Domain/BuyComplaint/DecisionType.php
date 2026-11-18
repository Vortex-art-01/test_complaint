<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

/**
 * Тип решения по лоту закупки.
 */
enum DecisionType: string
{
    case CancelPurchase = 'cancel_purchase';
    case ReviseResults = 'revise_results';
    case NoAction = 'no_action';

    /**
     * Решение требует действий организатора.
     */
    public function requiresExecution(): bool
    {
        return $this !== self::NoAction;
    }
}
