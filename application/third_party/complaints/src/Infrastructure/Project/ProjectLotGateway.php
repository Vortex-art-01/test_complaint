<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Project;

use Complaints\Application\Exception\IntegrationFailure;
use Complaints\Application\Port\LotDecisionAction;
use Complaints\Application\Port\ProcurementLotGateway;
use DateTimeImmutable;

/**
 * Сведения и действия системы закупок проекта по лотам.
 * Модуль меняет закупку только через cancelLot и returnLotForRevision.
 */
final class ProjectLotGateway implements ProcurementLotGateway
{
    public function procurementStatusName(int $buyId): string
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Вернуть название текущего статуса закупки на языке интерфейса
        // (как в реестре закупок) — колонка «Статус закупки» в реестре организатора.
        throw ProjectServiceMissing::in(__METHOD__);
    }

    /**
     * @throws IntegrationFailure
     */
    public function cancelLot(LotDecisionAction $action): void
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Отменить лот $action->buyLotId закупки $action->buyId так же, как кнопка
        // «Отмена лотов» → «Провести отмену» (макет 3.5–3.6): причина — решение
        // по жалобам; основание — решение № $action->decisionId от $action->decisionDate,
        // орган $action->authorityName, файл основания $action->basisFileId; от имени
        // $action->actorUserId. Протокол отмены формирует система закупок.
        // Ошибка — throw new IntegrationFailure(): действие модуля откатится.
        throw ProjectServiceMissing::in(__METHOD__);
    }

    /**
     * @throws IntegrationFailure
     */
    public function returnLotForRevision(LotDecisionAction $action): void
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Вернуть лот $action->buyLotId тендерной комиссии на пересмотр итогов (макет 3.7):
        // заявкам по лоту — статус «Рассмотрение заявки (пересмотр)», баннер с номерами
        // жалоб $action->complaintNumbers и решением № $action->decisionId, уведомление
        // членам комиссии. Ошибка — throw new IntegrationFailure().
        throw ProjectServiceMissing::in(__METHOD__);
    }

    public function revisionCompletedAt(int $buyLotId, DateTimeImmutable $since): ?DateTimeImmutable
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Вернуть дату публикации нового протокола итогов по лоту, опубликованного
        // позже $since (начало пересмотра); null — пересмотр ещё не завершён.
        // Вызывается задачей cron complete_revisions.
        throw ProjectServiceMissing::in(__METHOD__);
    }
}
