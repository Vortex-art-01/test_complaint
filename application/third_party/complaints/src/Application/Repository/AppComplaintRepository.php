<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Exception\NotFound;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Жалобы поставщиков и их лоты: test_app_complaint, test_app_complaint_lots.
 */
interface AppComplaintRepository
{
    /**
     * @throws NotFound жалобы нет или она удалена
     */
    public function get(int $complaintId): AppComplaint;

    /**
     * Блокирует строку жалобы до конца транзакции (SELECT … FOR UPDATE).
     *
     * @throws NotFound
     */
    public function lock(int $complaintId): AppComplaint;

    /**
     * Создаёт черновик; номер «Ж-…» присваивает БД.
     */
    public function create(NewAppComplaint $complaint, DateTimeImmutable $now): int;

    public function updateContent(int $complaintId, ?string $contactPhone, ?string $description, DateTimeImmutable $now): void;

    public function changeHead(int $complaintId, int $headUserId, DateTimeImmutable $now): void;

    public function changeAppealBasis(int $complaintId, DateTimeImmutable $protocolPublishedAt, Period $appealPeriod, DateTimeImmutable $now): void;

    /**
     * @param array<string, mixed>|null $snapshot реквизиты на момент формирования PDF; null — снять фиксацию
     */
    public function saveRequisitesSnapshot(int $complaintId, ?array $snapshot, DateTimeImmutable $now): void;

    /**
     * Условный переход: UPDATE … WHERE id = :id AND status = :from AND deleted_at IS NULL.
     *
     * @param array{submitted_at?: DateTimeImmutable, withdrawn_at?: DateTimeImmutable, buy_complaint_id?: int} $changes
     * @return bool false — статус уже другой
     */
    public function transition(int $complaintId, AppComplaintStatus $from, AppComplaintStatus $to, DateTimeImmutable $now, array $changes = []): bool;

    /**
     * Мягкое удаление, только в статусах до подачи.
     *
     * @return bool false — статус не позволяет
     */
    public function softDelete(int $complaintId, DateTimeImmutable $now): bool;

    /**
     * Блокирует лот заявки до конца транзакции, чтобы две жалобы
     * на него не создались одновременно (advisory-блокировка).
     */
    public function lockApplicationLot(int $appLotId): void;

    /**
     * Есть ли неудалённая жалоба на лот заявки с тем же окончанием срока обжалования.
     */
    public function hasActiveComplaint(int $appLotId, DateTimeImmutable $appealEndsAt, ?int $exceptComplaintId): bool;

    /**
     * Пары (закупка, окончание срока) с поданными жалобами, чей срок
     * обжалования закончился не позже момента.
     *
     * @return list<CollectionBatch>
     */
    public function collectionBatches(DateTimeImmutable $appealEndedBy): array;

    /**
     * Поданные жалобы пары, заблокированные до конца транзакции.
     *
     * @return list<AppComplaint>
     */
    public function lockSubmittedOfBatch(int $buyId, DateTimeImmutable $appealEndsAt): array;

    /**
     * Жалобы, собранные в решение.
     *
     * @return list<AppComplaint>
     */
    public function ofBuyComplaint(int $buyComplaintId): array;

    /**
     * Единственный неудалённый лот жалобы.
     *
     * @throws NotFound
     */
    public function lotOf(int $complaintId): AppComplaintLot;

    /**
     * @throws NotFound
     */
    public function lot(int $lotId): AppComplaintLot;

    public function addLot(int $complaintId, ApplicationLotCard $appLot, DateTimeImmutable $now): int;

    public function removeLot(int $lotId, DateTimeImmutable $now): void;

    /**
     * @return list<AppComplaintLot>
     */
    public function lotsOfBuyComplaintLot(int $buyComplaintLotId): array;

    /**
     * @return list<AppComplaintLot>
     */
    public function lotsOfBuyComplaint(int $buyComplaintId): array;

    public function linkToBuyComplaintLot(int $lotId, int $buyComplaintLotId, DateTimeImmutable $now): void;

    public function setVerdict(int $lotId, ?Verdict $verdict, DateTimeImmutable $now): void;

    public function setAnswer(int $lotId, ?int $answerId, DateTimeImmutable $now): void;

    /**
     * Снимает ссылки на ответы у всех лотов жалоб решения.
     */
    public function clearAnswers(int $buyComplaintId, DateTimeImmutable $now): void;

    /**
     * Отмечает лоты жалоб решения рассмотренными (reviewed_at).
     */
    public function markReviewed(int $buyComplaintId, DateTimeImmutable $now): void;
}
