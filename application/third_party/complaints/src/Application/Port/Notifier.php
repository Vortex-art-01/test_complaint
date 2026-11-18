<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Exception\IntegrationFailure;

/**
 * Уведомления в личный кабинет пользователя или организации.
 *
 * Вызывается внутри транзакции сценария: если уведомление не создано,
 * действие откатывается целиком.
 */
interface Notifier
{
    /**
     * @throws IntegrationFailure
     */
    public function notifyUser(int $userId, Notification $notification): void;

    /**
     * Уведомление всем пользователям организации.
     *
     * @throws IntegrationFailure
     */
    public function notifyOrganization(int $organizationId, Notification $notification): void;
}
