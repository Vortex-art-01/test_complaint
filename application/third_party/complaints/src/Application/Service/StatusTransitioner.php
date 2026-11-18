<?php

declare(strict_types=1);

namespace Complaints\Application\Service;

use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\StatusHistoryRepository;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintStateMachine;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStateMachine;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\History\HistoryEntityType;
use DateTimeImmutable;

/**
 * Переход статуса: проверка правил, условный UPDATE (… AND status = :from),
 * проверка числа затронутых строк и запись в журнал. Вызывается внутри
 * транзакции сценария; при конфликте бросает StatusConflict, и транзакция
 * откатывается целиком.
 */
final class StatusTransitioner
{
    public function __construct(
        private readonly AppComplaintRepository $complaints,
        private readonly BuyComplaintRepository $decisions,
        private readonly StatusHistoryRepository $history,
        private readonly AppComplaintStateMachine $complaintRules,
        private readonly BuyComplaintStateMachine $decisionRules,
    ) {
    }

    /**
     * @param array{submitted_at?: DateTimeImmutable, withdrawn_at?: DateTimeImmutable, buy_complaint_id?: int} $changes
     *
     * @throws StatusConflict
     */
    public function moveComplaint(
        AppComplaint $complaint,
        AppComplaintStatus $to,
        ?int $actorUserId,
        DateTimeImmutable $now,
        array $changes = [],
    ): void {
        if (!$this->complaintRules->canTransition($complaint->status, $to)
            || !$this->complaints->transition($complaint->id, $complaint->status, $to, $now, $changes)) {
            throw new StatusConflict();
        }

        $this->history->append(
            HistoryEntityType::AppComplaint,
            $complaint->id,
            $complaint->status->value,
            $to->value,
            $actorUserId,
            $now,
        );
    }

    /**
     * @param array<string, mixed> $changes см. BuyComplaintRepository::transition()
     *
     * @throws StatusConflict
     */
    public function moveDecision(
        BuyComplaint $decision,
        BuyComplaintStatus $to,
        ?int $actorUserId,
        DateTimeImmutable $now,
        array $changes = [],
        ?string $comment = null,
    ): void {
        if (!$this->decisionRules->canTransition($decision->status, $to)
            || !$this->decisions->transition($decision->id, $decision->status, $to, $now, $changes)) {
            throw new StatusConflict();
        }

        $this->history->append(
            HistoryEntityType::BuyComplaint,
            $decision->id,
            $decision->status->value,
            $to->value,
            $actorUserId,
            $now,
            $comment,
        );
    }

    /**
     * Переход исполнения решения по лоту.
     *
     * @throws StatusConflict
     */
    public function moveLotExecution(
        BuyComplaintLot $lot,
        LotExecutionStatus $to,
        ?int $actorUserId,
        DateTimeImmutable $at,
    ): void {
        if (!$this->decisions->moveLotExecution($lot->id, $lot->executionStatus, $to, $at, $actorUserId)) {
            throw new StatusConflict();
        }

        $this->history->append(
            HistoryEntityType::BuyComplaintLot,
            $lot->id,
            $lot->executionStatus?->value,
            $to->value,
            $actorUserId,
            $at,
        );
    }
}
