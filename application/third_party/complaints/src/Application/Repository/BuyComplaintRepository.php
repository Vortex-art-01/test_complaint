<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Exception\NotFound;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Решения по жалобам и решения по лотам: test_buy_complaint, test_buy_complaint_lots.
 */
interface BuyComplaintRepository
{
    /**
     * @throws NotFound
     */
    public function get(int $id): BuyComplaint;

    /**
     * Блокирует строку решения до конца транзакции.
     *
     * @throws NotFound
     */
    public function lock(int $id): BuyComplaint;

    /**
     * Создаёт решение; null — для пары (закупка, окончание срока) решение уже есть.
     */
    public function create(NewBuyComplaint $decision, DateTimeImmutable $now): ?int;

    public function findByBuyAndAppealEnd(int $buyId, DateTimeImmutable $appealEndsAt): ?BuyComplaint;

    /**
     * Условный переход: UPDATE … WHERE id = :id AND status = :from.
     *
     * @param array{
     *     coordinator_user_id?: ?int,
     *     approver_user_id?: int,
     *     sent_for_approval_at?: DateTimeImmutable,
     *     decision_number?: string,
     *     approved_at?: DateTimeImmutable,
     *     execution_status?: ExecutionStatus,
     *     execution_period?: Period
     * } $changes
     * @return bool false — статус уже другой
     */
    public function transition(int $id, BuyComplaintStatus $from, BuyComplaintStatus $to, DateTimeImmutable $now, array $changes = []): bool;

    /**
     * Закрепляет решение за рассматривающим, если оно ни за кем не закреплено.
     *
     * @return bool false — уже закреплено
     */
    public function claim(int $id, int $reviewerUserId, DateTimeImmutable $now): bool;

    public function release(int $id, DateTimeImmutable $now): void;

    public function setReviewMethod(int $id, ReviewMethod $method, DateTimeImmutable $now): void;

    /**
     * Исполнение решения завершено: in_progress -> executed.
     */
    public function markExecuted(int $id, DateTimeImmutable $now): bool;

    /**
     * Блокирует закупку до конца транзакции — для нумерации решений (advisory-блокировка).
     */
    public function lockBuy(int $buyId): void;

    /**
     * Сколько решений по закупке уже получили номер.
     */
    public function countNumberedForBuy(int $buyId): int;

    /**
     * Неутверждённые решения, срок рассмотрения которых заканчивается в [from, to).
     *
     * @return list<BuyComplaint>
     */
    public function reviewEndingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Решения на исполнении, срок исполнения которых заканчивается в [from, to).
     *
     * @return list<BuyComplaint>
     */
    public function executionEndingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return list<BuyComplaintLot>
     */
    public function lots(int $buyComplaintId): array;

    /**
     * @throws NotFound
     */
    public function lot(int $lotId): BuyComplaintLot;

    /**
     * Лот решения для лота закупки: создаёт или возвращает существующий.
     */
    public function ensureLot(int $buyComplaintId, int $buyLotId, DateTimeImmutable $now): int;

    public function setDecisionType(int $lotId, ?DecisionType $type, DateTimeImmutable $now): void;

    /**
     * Условный переход исполнения лота:
     *   null    -> pending   при утверждении;
     *   pending -> started   пересмотр итогов запущен (execution_started_at);
     *   pending -> executed  лот отменён (execution_started_at, executed_at);
     *   started -> executed  пересмотр завершён (executed_at).
     *
     * @return bool false — исполнение лота уже в другом статусе
     */
    public function moveLotExecution(int $lotId, ?LotExecutionStatus $from, LotExecutionStatus $to, DateTimeImmutable $at, ?int $actorUserId): bool;

    /**
     * Лоты, по которым идёт пересмотр итогов (started).
     *
     * @return list<BuyComplaintLot>
     */
    public function lotsUnderRevision(): array;
}
