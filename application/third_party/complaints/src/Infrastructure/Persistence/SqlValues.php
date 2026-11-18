<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Domain\Shared\Period;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use UnexpectedValueException;

/**
 * Значения столбцов PostgreSQL в типы PHP. timestamptz приходит строкой
 * в часовом поясе сессии и переводится в часовой пояс модуля.
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

    /**
     * @return array<string, mixed>|null
     *
     * @throws JsonException
     */
    public function jsonOrNull(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : throw new UnexpectedValueException('Ожидался JSON-объект.');
    }
}
