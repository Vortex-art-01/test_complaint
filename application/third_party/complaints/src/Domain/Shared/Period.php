<?php

declare(strict_types=1);

namespace Complaints\Domain\Shared;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Срок — полуинтервал [startsAt, endsAt).
 *
 * endsAt — 00:00 дня, следующего за последним днём срока. Срок открыт,
 * пока момент меньше endsAt. На экран выводится endsAt − 1 минута.
 */
final readonly class Period
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {
        if ($startsAt >= $endsAt) {
            throw new InvalidArgumentException('Начало срока должно быть раньше окончания.');
        }
    }

    public function isOpenAt(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->startsAt && $moment < $this->endsAt;
    }

    public function hasStartedAt(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->startsAt;
    }

    public function hasEndedAt(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->endsAt;
    }

    /**
     * Окончание срока для показа: 23:59 последнего дня.
     */
    public function displayEndsAt(): DateTimeImmutable
    {
        return $this->endsAt->sub(new DateInterval('PT1M'));
    }
}
