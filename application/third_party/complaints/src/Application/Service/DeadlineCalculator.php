<?php

declare(strict_types=1);

namespace Complaints\Application\Service;

use Complaints\Application\Port\WorkingCalendar;
use Complaints\Application\Shared\ComplaintSettings;
use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Сроки модуля в рабочих днях.
 *
 * Срок начинается в 00:00 первого рабочего дня после дня события и
 * заканчивается в 00:00 дня, следующего за последним рабочим днём срока.
 * Пример (3 р.д.): протокол 14.10 (ср) 18:00 -> [15.10 00:00, 20.10 00:00),
 * на экране «15.10 00:00 – 19.10 23:59».
 */
final class DeadlineCalculator
{
    public function __construct(
        private readonly WorkingCalendar $calendar,
        private readonly ComplaintSettings $settings,
    ) {
    }

    /**
     * Срок рассмотрения после последнего дня срока обжалования.
     */
    public function reviewPeriod(Period $appealPeriod): Period
    {
        $lastAppealDay = $this->calendar->addCalendarDays($this->dayOf($appealPeriod->endsAt), -1);

        return $this->periodAfterDayOf($lastAppealDay, $this->settings->reviewWorkingDays);
    }

    /**
     * Срок исполнения после утверждения решения.
     */
    public function executionPeriod(DateTimeImmutable $approvedAt): Period
    {
        return $this->periodAfterDayOf($approvedAt, $this->settings->executionWorkingDays);
    }

    private function periodAfterDayOf(DateTimeImmutable $event, int $workingDays): Period
    {
        $firstDay = $this->calendar->addWorkingDays($this->dayOf($event), 1);
        $lastDay = $this->calendar->addWorkingDays($firstDay, $workingDays - 1);

        return new Period($firstDay, $this->calendar->addCalendarDays($lastDay, 1));
    }

    private function dayOf(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone($this->settings->timeZone)->setTime(0, 0);
    }
}
