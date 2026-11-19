<?php

declare(strict_types=1);

namespace Complaints\Domain\Shared;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
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

    /**
     * Срок из календарных дней включительно: с 00:00 первого дня до 00:00 дня,
     * следующего за последним, в указанном часовом поясе.
     *
     * @param string $firstDay первый день, Y-m-d
     * @param string $lastDay  последний день, Y-m-d
     */
    public static function ofDays(string $firstDay, string $lastDay, DateTimeZone $timeZone): self
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $firstDay, $timeZone);
        $last = DateTimeImmutable::createFromFormat('!Y-m-d', $lastDay, $timeZone);
        if ($start === false || $last === false) {
            throw new InvalidArgumentException(sprintf('Ожидались даты Y-m-d: %s, %s.', $firstDay, $lastDay));
        }

        return new self($start, $last->modify('+1 day'));
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
