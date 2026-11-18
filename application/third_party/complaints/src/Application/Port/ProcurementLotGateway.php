<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Exception\IntegrationFailure;
use DateTimeImmutable;

/**
 * Действия и сведения системы закупок по лотам.
 */
interface ProcurementLotGateway
{
    /**
     * Дата и время публикации действующего протокола итогов по лоту;
     * от неё модуль считает срок обжалования. null — протокола нет.
     */
    public function resultsProtocolPublishedAt(int $buyLotId): ?DateTimeImmutable;

    /**
     * Название текущего статуса закупки на языке интерфейса — для реестра организатора.
     */
    public function procurementStatusName(int $buyId): string;

    /**
     * Отменяет лот по решению по жалобам (макет 3.5–3.6) и формирует протокол итогов.
     *
     * @throws IntegrationFailure
     */
    public function cancelLot(LotDecisionAction $action): void;

    /**
     * Возвращает лот на пересмотр итогов тендерной комиссией (макет 3.7):
     * статусы заявок «Рассмотрение заявки (пересмотр)», уведомление членам комиссии.
     *
     * @throws IntegrationFailure
     */
    public function returnLotForRevision(LotDecisionAction $action): void;

    /**
     * Момент завершения пересмотра — публикации нового протокола по лоту
     * после указанного момента; null — пересмотр ещё не завершён.
     */
    public function revisionCompletedAt(int $buyLotId, DateTimeImmutable $since): ?DateTimeImmutable;
}
