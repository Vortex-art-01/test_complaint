<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Notification;

use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use InvalidArgumentException;

/**
 * Тексты уведомлений из языковых файлов модуля
 * (ключ complaints_notification_<тип>, подстановки {number}, {buy}, {count}, {date}, {decision}).
 */
final class NotificationTexts
{
    /**
     * @param array<string, string> $russian строки application/language/russian/complaints_lang.php
     * @param array<string, string> $kazakh  строки application/language/kazakh/complaints_lang.php
     */
    public function __construct(
        private readonly array $russian,
        private readonly array $kazakh,
    ) {
    }

    public function message(Notification $notification): NotificationMessage
    {
        $key = 'complaints_notification_' . $notification->type->value;

        return new NotificationMessage(
            self::fill($this->russian[$key] ?? throw new InvalidArgumentException('Нет строки ' . $key), $notification->params),
            self::fill($this->kazakh[$key] ?? $this->russian[$key], $notification->params),
            self::path($notification->target) . $notification->targetId,
        );
    }

    private static function path(NotificationTarget $target): string
    {
        return match ($target) {
            NotificationTarget::Complaint => 'complaint_supplier/show/',
            NotificationTarget::Decision => 'complaint_review/show/',
            NotificationTarget::Execution => 'complaint_execution/show/',
        };
    }

    /**
     * @param array<string, string> $params
     */
    private static function fill(string $text, array $params): string
    {
        $replacements = [];
        foreach ($params as $name => $value) {
            $replacements['{' . $name . '}'] = $value;
        }

        return strtr($text, $replacements);
    }
}
