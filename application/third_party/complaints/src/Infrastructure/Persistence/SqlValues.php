<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Domain\Shared\Period;
use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

/**
 * Значения столбцов PostgreSQL в типы PHP. timestamptz приходит строкой
 * в часовом поясе сессии и переводится в часовой пояс модуля; date —
 * строкой Y-m-d и читается как день в часовом поясе модуля.
 */
final class SqlValues
{
    public function __construct(
        private readonly DateTimeZone $timeZone,
    ) {
    }

    public function moment(mixed $value): DateTimeImmutable
    {
        return $this->momentOrNull($value) ?? throw new UnexpectedValueException('Ожидалась дата, получен NULL.');
    }

    public function momentOrNull(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return (new DateTimeImmutable((string) $value))->setTimezone($this->timeZone);
    }

    public function period(mixed $startsAt, mixed $endsAt): Period
    {
        return new Period($this->moment($startsAt), $this->moment($endsAt));
    }

    public function periodOrNull(mixed $startsAt, mixed $endsAt): ?Period
    {
        return $startsAt === null || $endsAt === null ? null : $this->period($startsAt, $endsAt);
    }

    /**
     * Срок из столбцов date: с первого дня по последний включительно.
     */
    public function daysOrNull(mixed $firstDay, mixed $lastDay): ?Period
    {
        return $firstDay === null || $lastDay === null
            ? null
            : Period::ofDays((string) $firstDay, (string) $lastDay, $this->timeZone);
    }

    /**
     * День момента в часовом поясе модуля, Y-m-d — для сравнения со столбцами date.
     */
    public function dayOf(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone($this->timeZone)->format('Y-m-d');
    }

    public function int(mixed $value): int
    {
        return $this->intOrNull($value) ?? throw new UnexpectedValueException('Ожидалось число, получен NULL.');
    }

    public function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    public function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === 't' || $value === 'true' || $value === '1';
    }
}
