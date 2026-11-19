<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

/**
 * Статус исполнения решения по лоту: следует из дат trd_complaint_lots
 * (execution_started_at, executed_at).
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
