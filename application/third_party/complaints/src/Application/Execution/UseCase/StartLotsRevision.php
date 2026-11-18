<?php

declare(strict_types=1);

namespace Complaints\Application\Execution\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\IntegrationFailure;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Execution\LotActions;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\ProcurementLotGateway;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;

/**
 * «Пересмотр итогов»: лоты возвращаются тендерной комиссии на пересмотр.
 * Лот исполнен, когда система закупок опубликует новый протокол
 * (задача CompleteLotRevisions).
 */
final class StartLotsRevision
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly BuyComplaintRepository $decisions,
        private readonly LotActions $lotActions,
        private readonly ProcurementLotGateway $lotGateway,
        private readonly StatusTransitioner $transitions,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return int сколько лотов возвращено на пересмотр
     *
     * @throws AccessDenied
     * @throws StatusConflict нечего пересматривать
     * @throws IntegrationFailure
     */
    public function execute(int $decisionId): int
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use ($user, $decisionId, $now): int {
            $decision = $this->decisions->lock($decisionId);
            $this->guard->assertAllowed($this->guard->isOrganizerOf($user, $decision));
            if ($decision->executionStatus !== ExecutionStatus::InProgress) {
                throw new StatusConflict();
            }

            $lots = array_values(array_filter(
                $this->decisions->lots($decisionId),
                static fn (BuyComplaintLot $lot): bool => $lot->isAwaitingExecution(DecisionType::ReviseResults),
            ));
            if ($lots === []) {
                throw new StatusConflict('complaints_error_nothing_to_execute');
            }

            foreach ($lots as $lot) {
                $this->lotGateway->returnLotForRevision($this->lotActions->basis($decision, $lot, $user->userId));
                $this->transitions->moveLotExecution($lot, LotExecutionStatus::Started, $user->userId, $now);
            }

            return count($lots);
        });
    }
}
