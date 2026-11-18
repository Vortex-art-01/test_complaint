<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

/**
 * Статус исполнения решения по лоту (test_buy_complaint_lots.execution_status).
 */
enum LotExecutionStatus: string
{
    case Pending = 'pending';
    case Started = 'started';
    case Executed = 'executed';

    /**
     * Статус лота в момент утверждения решения; null — исполнение не нужно.
     */
    public static function afterApproval(DecisionType $type): ?self
    {
        return $type->requiresExecution() ? self::Pending : null;
    }
}
