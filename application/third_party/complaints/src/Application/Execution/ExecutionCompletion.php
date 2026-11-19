<?php

declare(strict_types=1);

namespace Complaints\Application\Execution;

use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use DateTimeImmutable;

/**
 * Все лоты исполнены — решение исполнено: execution_status = executed.
 */
final class ExecutionCompletion
{
    public function __construct(
        private readonly BuyComplaintRepository $decisions,
        private readonly ProcurementRepository $procurement,
        private readonly Notifier $notifier,
    ) {
    }

    /**
     * @return bool true — решение стало исполненным
     */
    public function completeIfDone(BuyComplaint $decision, DateTimeImmutable $at): bool
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

        $buy = $this->procurement->buy($decision->buyId);
        $this->notifier->notifyOrganization($buy->organizerOrgId, new Notification(
            NotificationType::DecisionExecuted,
            ['buy' => $buy->number, 'decision' => (string) $decision->id],
            NotificationTarget::Execution,
            $decision->id,
        ));

        return true;
    }
}
