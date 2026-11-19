<?php

declare(strict_types=1);

namespace Complaints\Application\Service;

use Complaints\Application\Access\Actor;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintStateMachine;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStateMachine;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use DateTimeImmutable;

/**
 * Переходы статусов: проверка правил предметной области и условное
 * изменение строки. Если статус успел измениться — StatusConflict.
 */
final class StatusTransitioner
{
    public function __construct(
        private readonly AppComplaintRepository $complaints,
        private readonly BuyComplaintRepository $decisions,
        private readonly AppComplaintStateMachine $complaintRules,
        private readonly BuyComplaintStateMachine $decisionRules,
    ) {
    }

    /**
     * @param array<string, mixed> $changes см. AppComplaintRepository::transition()
     *
     * @throws StatusConflict
     */
    public function moveComplaint(AppComplaint $complaint, AppComplaintStatus $to, DateTimeImmutable $now, array $changes = []): void
    {
        if (!$this->complaintRules->canTransition($complaint->status, $to)
            || !$this->complaints->transition($complaint->id, $complaint->status, $to, $now, $changes)) {
            throw new StatusConflict();
        }
    }

    /**
     * @param array<string, mixed> $changes см. BuyComplaintRepository::transition()
     *
     * @throws StatusConflict
     */
    public function moveDecision(BuyComplaint $decision, BuyComplaintStatus $to, DateTimeImmutable $now, array $changes = []): void
    {
        if (!$this->decisionRules->canTransition($decision->status, $to)
            || !$this->decisions->transition($decision->id, $decision->status, $to, $now, $changes)) {
            throw new StatusConflict();
        }
    }

    /**
     * Переход исполнения решения по лоту.
     *
     * @param Actor|null $actor кто начал исполнение; null — система
     *
     * @throws StatusConflict
     */
    public function moveLotExecution(BuyComplaintLot $lot, LotExecutionStatus $to, DateTimeImmutable $at, ?Actor $actor): void
    {
        if (!$this->decisions->moveLotExecution($lot->id, $lot->executionStatus, $to, $at, $actor)) {
            throw new StatusConflict();
        }
    }
}
