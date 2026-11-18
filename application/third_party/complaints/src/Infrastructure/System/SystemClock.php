<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\System;

use Complaints\Application\Port\Clock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Текущее время сервера в часовом поясе модуля (Asia/Almaty, UTC+05:00).
 */
final class SystemClock implements Clock
{
    public function __construct(
        private readonly DateTimeZone $timeZone,
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timeZone);
    }
}
