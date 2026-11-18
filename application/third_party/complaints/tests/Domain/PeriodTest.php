<?php

declare(strict_types=1);

namespace Complaints\Tests\Domain;

use Complaints\Domain\Shared\Period;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PeriodTest extends TestCase
{
    private function appealPeriod(): Period
    {
        return new Period(
            new DateTimeImmutable('2026-10-15 00:00:00+05:00'),
            new DateTimeImmutable('2026-10-20 00:00:00+05:00'),
        );
    }

    public function testIsOpenFromStartUntilEndExclusive(): void
    {
        $period = $this->appealPeriod();

        self::assertFalse($period->isOpenAt(new DateTimeImmutable('2026-10-14 23:59:59+05:00')));
        self::assertTrue($period->isOpenAt(new DateTimeImmutable('2026-10-15 00:00:00+05:00')));
        self::assertTrue($period->isOpenAt(new DateTimeImmutable('2026-10-19 23:59:59+05:00')));
        self::assertFalse($period->isOpenAt(new DateTimeImmutable('2026-10-20 00:00:00+05:00')));
    }

    public function testHasEndedAtEndBoundary(): void
    {
        $period = $this->appealPeriod();

        self::assertFalse($period->hasEndedAt(new DateTimeImmutable('2026-10-19 23:59:59+05:00')));
        self::assertTrue($period->hasEndedAt(new DateTimeImmutable('2026-10-20 00:00:00+05:00')));
    }

    public function testDisplayEndIsOneMinuteBeforeEnd(): void
    {
        self::assertSame(
            '2026-10-19 23:59',
            $this->appealPeriod()->displayEndsAt()->format('Y-m-d H:i'),
        );
    }

    public function testComparesMomentsInDifferentTimeZones(): void
    {
        self::assertTrue($this->appealPeriod()->isOpenAt(new DateTimeImmutable('2026-10-19 18:59:59+00:00')));
        self::assertFalse($this->appealPeriod()->isOpenAt(new DateTimeImmutable('2026-10-19 19:00:00+00:00')));
    }

    public function testRejectsEmptyOrReversedPeriod(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Period(
            new DateTimeImmutable('2026-10-20 00:00:00+05:00'),
            new DateTimeImmutable('2026-10-20 00:00:00+05:00'),
        );
    }
}
