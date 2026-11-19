<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

/**
 * Статус исполнения решения: часть статуса утверждённого решения (trd_complaint.status_id 5, 6, 7).
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
