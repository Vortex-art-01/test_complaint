<?php

declare(strict_types=1);

namespace Complaints\Application\Shared;

use Complaints\Domain\Shared\Period;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Даты для документов и уведомлений: в часовом поясе модуля, «дд.мм.гггг чч:мм».
 */
final class DisplayDate
{
    public function __construct(
        private readonly DateTimeZone $timeZone,
    ) {
    }

    public function moment(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone($this->timeZone)->format('d.m.Y H:i');
    }

    public function day(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone($this->timeZone)->format('d.m.Y');
    }

    /**
     * Срок для показа: начало и окончание в 23:59 последнего дня.
     *
     * @return array{from: string, to: string}
     */
    public function period(Period $period): array
    {
        return ['from' => $this->moment($period->startsAt), 'to' => $this->moment($period->displayEndsAt())];
    }
}
