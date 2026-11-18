<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Port\WorkingCalendar;
use DateTimeImmutable;

final class FakeCalendar implements WorkingCalendar
{
    /** @var list<string> праздники Y-m-d */
    public array $holidays = [];

    public function addWorkingDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        $day = $date;
        while ($days > 0) {
            $day = $day->modify('+1 day');
            if ((int) $day->format('N') < 6 && !in_array($day->format('Y-m-d'), $this->holidays, true)) {
                $days--;
            }
        }

        return $day;
    }

    public function addCalendarDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        return $date->modify(sprintf('%+d day', $days));
    }
}
