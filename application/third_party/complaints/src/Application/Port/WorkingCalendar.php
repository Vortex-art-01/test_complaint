<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use DateTimeImmutable;

/**
 * Производственный календарь: выходные, праздники и переносы рабочих дней.
 *
 * Даты передаются и возвращаются на 00:00 в часовом поясе модуля.
 */
interface WorkingCalendar
{
    /**
     * Дата через N рабочих дней после даты; сама дата не считается.
     * N = 0 возвращает исходную дату; N = 1 — ближайший следующий рабочий день.
     */
    public function addWorkingDays(DateTimeImmutable $date, int $days): DateTimeImmutable;

    /**
     * Дата через N календарных дней.
     */
    public function addCalendarDays(DateTimeImmutable $date, int $days): DateTimeImmutable;
}
