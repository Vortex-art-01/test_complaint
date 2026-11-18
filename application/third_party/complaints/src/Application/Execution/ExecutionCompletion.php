<?php

declare(strict_types=1);

namespace Complaints\Application\Execution;

use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Repository\StatusHistoryRepository;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\History\HistoryEntityType;
use DateTimeImmutable;

/**
 * Все лоты исполнены — решение исполнено: execution_status = executed.
 */
final class ExecutionCompletion
{
    public function __construct(
        private readonly BuyComplaintRepository $decisions,
        private readonly StatusHistoryRepository $history,
        private readonly ProcurementRepository $procurement,
        private readonly Notifier $notifier,
    ) {
    }

    /**
     * @return bool true — решение стало исполненным
     */
    public function completeIfDone(BuyComplaint $decision, ?int $actorUserId, DateTimeImmutable $at): bool
    {
        if ($decision->executionStatus !== ExecutionStatus::InProgress) {
            return false;
        }
        foreach ($this->decisions->lots($decision->id) as $lot) {
            if ($lot->executionStatus !== null && $lot->executionStatus !== LotExecutionStatus::Executed) {
                return false;
            }
        }
        if (!$this->decisions->markExecuted($decision->id, $at)) {
            return false;
        }

        $this->history->append(
            HistoryEntityType::BuyComplaint,
            $decision->id,
            ExecutionStatus::InProgress->value,
            ExecutionStatus::Executed->value,
            $actorUserId,
            $at,
            'execution_status',
        );

        $buy = $this->procurement->buy($decision->buyId);
        $this->notifier->notifyOrganization($buy->organizerOrgId, new Notification(
            NotificationType::DecisionExecuted,
            ['buy' => $buy->number, 'decision' => (string) $decision->decisionNumber],
            NotificationTarget::Execution,
            $decision->id,
        ));

        return true;
    }
}
