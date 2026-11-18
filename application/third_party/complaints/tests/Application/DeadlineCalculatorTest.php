<?php

declare(strict_types=1);

namespace Complaints\Tests\Application;

use Complaints\Application\Service\DeadlineCalculator;
use Complaints\Domain\Shared\Period;
use Complaints\Tests\Support\World;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Сроки из макетов: 3 р.д. обжалования, 3 р.д. рассмотрения, 2 р.д. исполнения.
 */
final class DeadlineCalculatorTest extends TestCase
{
    private World $world;

    protected function setUp(): void
    {
        $this->world = new World();
    }

    private function calculator(): DeadlineCalculator
    {
        return $this->world->get(DeadlineCalculator::class);
    }

    private static function shown(Period $period): string
    {
        return $period->startsAt->format('Y-m-d H:i') . ' – ' . $period->displayEndsAt()->format('Y-m-d H:i');
    }

    public function testAppealStartsNextWorkingDayAndLastsThreeWorkingDays(): void
    {
        $period = $this->calculator()->appealPeriod(new DateTimeImmutable('2026-10-14 18:00:00+05:00'));

        self::assertSame('2026-10-15 00:00 – 2026-10-19 23:59', self::shown($period));
        self::assertSame('2026-10-20 00:00:00+05:00', $period->endsAt->format('Y-m-d H:i:sP'));
    }

    public function testReviewStartsOnFirstWorkingDayAfterAppeal(): void
    {
        $calculator = $this->calculator();

        $afterMonday = $calculator->reviewPeriod($calculator->appealPeriod(new DateTimeImmutable('2026-10-14 18:00:00+05:00')));
        self::assertSame('2026-10-20 00:00 – 2026-10-22 23:59', self::shown($afterMonday));

        $afterFriday = $calculator->reviewPeriod($calculator->appealPeriod(new DateTimeImmutable('2026-10-13 18:00:00+05:00')));
        self::assertSame('2026-10-19 00:00 – 2026-10-21 23:59', self::shown($afterFriday));
    }

    public function testExecutionLastsTwoWorkingDaysAfterApproval(): void
    {
        $period = $this->calculator()->executionPeriod(new DateTimeImmutable('2026-10-21 14:21:00+05:00'));

        self::assertSame('2026-10-22 00:00 – 2026-10-23 23:59', self::shown($period));
    }

    public function testHolidaysAreSkipped(): void
    {
        $this->world->calendar->holidays = ['2026-10-26'];

        $period = $this->calculator()->appealPeriod(new DateTimeImmutable('2026-10-22 18:00:00+05:00'));

        self::assertSame('2026-10-23 00:00 – 2026-10-28 23:59', self::shown($period));
    }

    public function testEventIsTakenInModuleTimeZone(): void
    {
        $period = $this->calculator()->appealPeriod(new DateTimeImmutable('2026-10-14 20:30:00+00:00'));

        self::assertSame('2026-10-16 00:00 – 2026-10-20 23:59', self::shown($period));
    }
}
