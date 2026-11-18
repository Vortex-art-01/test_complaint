<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\System;

use Complaints\Application\Port\WorkingCalendar;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Производственный календарь из конфига: суббота и воскресенье — выходные,
 * праздники и перенесённые рабочие дни задаются списками дат Y-m-d
 * (config/complaints.php → holidays, working_days).
 *
 * Если в проекте уже есть производственный календарь, его можно подключить
 * своим адаптером порта WorkingCalendar вместо этого класса.
 */
final class ConfiguredWorkingCalendar implements WorkingCalendar
{
    private const DATE = '/^\d{4}-\d{2}-\d{2}$/';

    /** @var array<string, true> */
    private array $holidays;

    /** @var array<string, true> */
    private array $workingDays;

    /**
     * @param list<string> $holidays    нерабочие праздничные дни
     * @param list<string> $workingDays рабочие субботы и воскресенья (переносы)
     */
    public function __construct(array $holidays, array $workingDays)
    {
        $this->holidays = self::dates($holidays, 'holidays');
        $this->workingDays = self::dates($workingDays, 'working_days');
    }

    public function addWorkingDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        if ($days < 0) {
            throw new InvalidArgumentException('Число рабочих дней не может быть отрицательным.');
        }

        $day = $date;
        while ($days > 0) {
            $day = $day->modify('+1 day');
            if ($this->isWorking($day)) {
                $days--;
            }
        }

        return $day;
    }

    public function addCalendarDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        return $date->modify(sprintf('%+d day', $days));
    }

    public function isWorking(DateTimeImmutable $day): bool
    {
        $key = $day->format('Y-m-d');
        if (isset($this->workingDays[$key])) {
            return true;
        }

        return !isset($this->holidays[$key]) && (int) $day->format('N') < 6;
    }

    /**
     * @param list<string> $dates
     * @return array<string, true>
     */
    private static function dates(array $dates, string $name): array
    {
        $set = [];
        foreach ($dates as $date) {
            if (!is_string($date) || preg_match(self::DATE, $date) !== 1) {
                throw new InvalidArgumentException(sprintf('complaints[%s]: ожидается дата Y-m-d, получено %s.', $name, var_export($date, true)));
            }
            $set[$date] = true;
        }

        return $set;
    }
}
