<?php

declare(strict_types=1);

namespace Complaints\Tests\Application;

use Complaints\Application\Service\DeadlineCalculator;
use Complaints\Domain\Shared\Period;
use Complaints\Tests\Support\World;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Сроки из макетов: обжалование устанавливает проект (15–19.10.2026),
 * рассмотрение — 3 рабочих дня, исполнение — 2 рабочих дня.
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

    private static function days(string $firstDay, string $lastDay): Period
    {
        return Period::ofDays($firstDay, $lastDay, new DateTimeZone('Asia/Almaty'));
    }

    private static function shown(Period $period): string
    {
        return $period->startsAt->format('Y-m-d H:i') . ' – ' . $period->displayEndsAt()->format('Y-m-d H:i');
    }

    public function testAppealDaysLastUntilMidnightAfterTheLastDay(): void
    {
        $period = self::days('2026-10-15', '2026-10-19');

        self::assertSame('2026-10-15 00:00 – 2026-10-19 23:59', self::shown($period));
        self::assertSame('2026-10-20 00:00:00+05:00', $period->endsAt->format('Y-m-d H:i:sP'));
    }

    public function testReviewStartsOnFirstWorkingDayAfterAppeal(): void
    {
        $calculator = $this->calculator();

        self::assertSame('2026-10-20 00:00 – 2026-10-22 23:59', self::shown($calculator->reviewPeriod(self::days('2026-10-15', '2026-10-19'))));
        self::assertSame('2026-10-19 00:00 – 2026-10-21 23:59', self::shown($calculator->reviewPeriod(self::days('2026-10-14', '2026-10-16'))));
    }

    public function testExecutionLastsTwoWorkingDaysAfterApproval(): void
    {
        $period = $this->calculator()->executionPeriod(new DateTimeImmutable('2026-10-21 14:21:00+05:00'));

        self::assertSame('2026-10-22 00:00 – 2026-10-23 23:59', self::shown($period));
    }

    public function testHolidaysAreSkipped(): void
    {
        $this->world->calendar->holidays = ['2026-10-26'];

        $period = $this->calculator()->reviewPeriod(self::days('2026-10-21', '2026-10-23'));

        self::assertSame('2026-10-27 00:00 – 2026-10-29 23:59', self::shown($period));
    }

    public function testApprovalIsTakenInModuleTimeZone(): void
    {
        $period = $this->calculator()->executionPeriod(new DateTimeImmutable('2026-10-21 20:30:00+00:00'));

        self::assertSame('2026-10-23 00:00 – 2026-10-26 23:59', self::shown($period));
    }
}
