<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Port\Clock;
use DateTimeImmutable;

/**
 * Свой адаптер часов «проекта» — для проверки ключа adapters конфига.
 */
final class FixedNoonClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-16 12:00:00+05:00');
    }
}
