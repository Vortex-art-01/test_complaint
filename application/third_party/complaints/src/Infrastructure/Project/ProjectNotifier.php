<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Project;

use Complaints\Application\Exception\IntegrationFailure;
use Complaints\Application\Port\Notification;
use Complaints\Application\Port\Notifier;
use Complaints\Infrastructure\Notification\NotificationMessage;
use Complaints\Infrastructure\Notification\NotificationTexts;

/**
 * Уведомления в личный кабинет через сервис уведомлений проекта.
 * Текст и ссылку строит модуль, доставку выполняет проект.
 */
final class ProjectNotifier implements Notifier
{
    public function __construct(
        private readonly NotificationTexts $texts,
    ) {
    }

    public function notifyUser(int $userId, Notification $notification): void
    {
        $this->deliverToUser($userId, $this->texts->message($notification));
    }

    public function notifyOrganization(int $organizationId, Notification $notification): void
    {
        $this->deliverToOrganization($organizationId, $this->texts->message($notification));
    }

    /**
     * @throws IntegrationFailure
     */
    private function deliverToUser(int $userId, NotificationMessage $message): void
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Создать уведомление в личном кабинете пользователя $userId: текст
        // $message->in(<язык пользователя: 'ru' или 'kz'>), ссылка
        // '/' . <язык> . '/' . $message->path. Вызов идёт внутри транзакции сценария;
        // если уведомление не создано — throw new IntegrationFailure(), действие откатится.
        throw ProjectServiceMissing::in(__METHOD__);
    }

    /**
     * @throws IntegrationFailure
     */
    private function deliverToOrganization(int $organizationId, NotificationMessage $message): void
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // То же уведомление всем активным пользователям организации $organizationId
        // (поставщику — о рассмотренной жалобе, организатору — о решении к исполнению).
        throw ProjectServiceMissing::in(__METHOD__);
    }
}
