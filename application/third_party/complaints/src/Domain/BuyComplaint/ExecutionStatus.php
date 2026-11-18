<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

/**
 * Статус исполнения решения (test_buy_complaint.execution_status).
 */
enum ExecutionStatus: string
{
    case NotRequired = 'not_required';
    case InProgress = 'in_progress';
    case Executed = 'executed';

    /**
     * Статус исполнения в момент утверждения решения.
     *
     * @param list<DecisionType> $decisionTypes типы решений по всем лотам
     */
    public static function afterApproval(array $decisionTypes): self
    {
        foreach ($decisionTypes as $type) {
            if ($type->requiresExecution()) {
                return self::InProgress;
            }
        }

        return self::NotRequired;
    }
}
