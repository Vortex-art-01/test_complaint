<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Notification;

/**
 * Готовое уведомление: текст на двух языках и адрес страницы без префикса языка.
 */
final readonly class NotificationMessage
{
    /**
     * @param string $path например «complaint_supplier/show/15»; ссылка — «/ru/» . $path
     */
    public function __construct(
        public string $ru,
        public string $kz,
        public string $path,
    ) {
    }

    public function in(string $language): string
    {
        return $language === 'kz' ? $this->kz : $this->ru;
    }
}
