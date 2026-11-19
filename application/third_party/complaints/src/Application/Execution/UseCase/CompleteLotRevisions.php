<?php

declare(strict_types=1);

namespace Complaints\Application\Execution\UseCase;

use Complaints\Application\Execution\ExecutionCompletion;
use Complaints\Application\Port\ProcurementLotGateway;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;

/**
 * Завершение пересмотра итогов (cron): лот исполнен, когда система закупок
 * опубликовала новый протокол после начала пересмотра. Тот же сценарий
 * можно вызвать из кода публикации протокола — повторный вызов безопасен.
 */
final class CompleteLotRevisions
{
    public function __construct(
        private readonly BuyComplaintRepository $decisions,
        private readonly ProcurementLotGateway $lotGateway,
        private readonly StatusTransitioner $transitions,
        private readonly ExecutionCompletion $completion,
        private readonly TransactionRunner $transactions,
    ) {
    }

    /**
     * @return int сколько лотов отмечено исполненными
     */
    public function execute(): int
    {
        $completed = 0;
        foreach ($this->decisions->lotsUnderRevision() as $lot) {
            if ($lot->executionStartedAt === null) {
                continue;
            }
            $completedAt = $this->lotGateway->revisionCompletedAt($lot->buyLotId, $lot->executionStartedAt);
            if ($completedAt === null) {
                continue;
            }

            $completed += $this->transactions->transactional(function () use ($lot, $completedAt): int {
                $decision = $this->decisions->lock($lot->buyComplaintId);
                $fresh = $this->decisions->lot($lot->id);
                if ($fresh->executionStatus !== LotExecutionStatus::Started) {
                    return 0;
                }

                $this->transitions->moveLotExecution($fresh, LotExecutionStatus::Executed, $completedAt, null);
                $this->completion->completeIfDone($decision, $completedAt);

                return 1;
            });
        }

        return $completed;
    }
}
