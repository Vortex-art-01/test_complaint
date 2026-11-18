<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

/**
 * Уведомление: вид, параметры текста и страница для ссылки.
 */
final readonly class Notification
{
    /**
     * @param array<string, string> $params значения для подстановки в текст: {number}, {buy}, {lot}, {date}
     */
    public function __construct(
        public NotificationType $type,
        public array $params,
        public NotificationTarget $target,
        public int $targetId,
    ) {
    }
}
