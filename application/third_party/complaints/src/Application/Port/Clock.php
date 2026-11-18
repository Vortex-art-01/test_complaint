<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use DateTimeImmutable;

/**
 * Текущее время в часовом поясе модуля. В тестах подменяется.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
