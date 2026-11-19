<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Exception\NotFound;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\Verdict;
use DateTimeImmutable;

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

    public function create(AppComplaintDraft $complaint, DateTimeImmutable $now): int;

    public function updateContent(int $complaintId, ?string $contactPhone, ?string $description, DateTimeImmutable $now): void;

    public function changeHead(int $complaintId, int $headUserId, DateTimeImmutable $now): void;

    /**
     * Условный переход: UPDATE … WHERE id = :id AND status = :from AND deleted_at IS NULL.
     *
     * @param array{submitted_at?: DateTimeImmutable, submitted_by_user_id?: int, buy_complaint_id?: int} $changes
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
     * Есть ли на лот заявки неудалённая жалоба, ещё не собранная в решение.
     */
    public function hasActiveComplaint(int $appLotId, ?int $exceptComplaintId): bool;

    /**
     * Закупки с поданными и ещё не собранными жалобами, срок обжалования
     * которых закончился не позже момента.
     *
     * @return list<CollectionBatch>
     */
    public function collectionBatches(DateTimeImmutable $appealEndedBy): array;

    /**
     * Поданные и ещё не собранные жалобы закупки, заблокированные до конца транзакции.
     *
     * @return list<AppComplaint>
     */
    public function lockSubmittedOfBuy(int $buyId): array;

    /**
     * Жалобы, собранные в решение.
     *
     * @return list<AppComplaint>
     */
    public function ofBuyComplaint(int $buyComplaintId): array;

    /**
     * Лот жалобы.
     *
     * @throws NotFound
     */
    public function lotOf(int $complaintId): AppComplaintLot;

    /**
     * @throws NotFound
     */
    public function lot(int $lotId): AppComplaintLot;

    public function addLot(int $complaintId, ApplicationLotCard $appLot, DateTimeImmutable $now): int;

    /**
     * Перенос лота черновика на другой лот той же заявки.
     */
    public function changeLot(int $lotId, ApplicationLotCard $appLot, DateTimeImmutable $now): void;

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
}
