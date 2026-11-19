<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Access\Actor;
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
     * Создаёт решение со сроками обжалования и рассмотрения;
     * null — для этой закупки и этого срока обжалования решение уже есть.
     */
    public function create(BuyComplaintCycle $decision, DateTimeImmutable $now): ?int;

    public function findByBuyAndAppealEnd(int $buyId, DateTimeImmutable $appealEndsAt): ?BuyComplaint;

    /**
     * Условный переход: UPDATE … WHERE id = :id AND status = :from.
     *
     * @param array{
     *     sent_for_approval_at?: DateTimeImmutable,
     *     sent_by?: Actor,
     *     coordinated_at?: DateTimeImmutable,
     *     approved_at?: DateTimeImmutable,
     *     recalled_at?: DateTimeImmutable,
     *     recalled_by?: Actor,
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
    public function claim(int $id, Actor $reviewer, DateTimeImmutable $now): bool;

    /**
     * Снимает закрепление за рассматривающим.
     */
    public function release(int $id, DateTimeImmutable $now): void;

    /**
     * Назначает согласующего (необязателен) и утверждающего; прежние снимаются.
     */
    public function assignRoute(int $id, ?Actor $coordinator, Actor $approver, Actor $assignedBy, DateTimeImmutable $now): void;

    /**
     * Отзыв: снимает согласующего и утверждающего и очищает даты маршрута.
     */
    public function resetRoute(int $id, DateTimeImmutable $now): void;

    public function setReviewMethod(int $id, ReviewMethod $method, DateTimeImmutable $now): void;

    /**
     * Исполнение решения завершено: на исполнении -> исполнено.
     */
    public function markExecuted(int $id, DateTimeImmutable $now): bool;

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

    /**
     * Пересчитывает количество лотов и жалоб решения после сбора.
     */
    public function refreshCounts(int $id): void;

    public function setDecisionType(int $lotId, ?DecisionType $type, DateTimeImmutable $now): void;

    /**
     * Условный переход исполнения лота:
     *   null    -> pending   при утверждении;
     *   pending -> started   пересмотр итогов запущен;
     *   pending -> executed  лот отменён;
     *   started -> executed  пересмотр завершён.
     *
     * @param Actor|null $actor кто начал исполнение; null — система
     * @return bool false — исполнение лота уже в другом статусе
     */
    public function moveLotExecution(int $lotId, ?LotExecutionStatus $from, LotExecutionStatus $to, DateTimeImmutable $at, ?Actor $actor): bool;

    /**
     * Лоты, по которым идёт пересмотр итогов.
     *
     * @return list<BuyComplaintLot>
     */
    public function lotsUnderRevision(): array;
}
