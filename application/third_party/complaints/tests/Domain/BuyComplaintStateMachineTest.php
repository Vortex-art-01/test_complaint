<?php

declare(strict_types=1);

namespace Complaints\Tests\Domain;

use Complaints\Domain\BuyComplaint\BuyComplaintStateMachine;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus as Status;
use Complaints\Domain\Shared\InvalidStatusTransition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BuyComplaintStateMachineTest extends TestCase
{
    /**
     * @return list<array{Status, Status}>
     */
    private static function allowed(): array
    {
        return [
            [Status::InReview, Status::OnCoordination],
            [Status::InReview, Status::OnApproval],
            [Status::OnCoordination, Status::OnApproval],
            [Status::OnCoordination, Status::Recalled],
            [Status::OnApproval, Status::Approved],
            [Status::OnApproval, Status::Recalled],
            [Status::Recalled, Status::OnCoordination],
            [Status::Recalled, Status::OnApproval],
            [Status::Recalled, Status::InReview],
        ];
    }

    /**
     * @return iterable<string, array{Status, Status, bool}>
     */
    public static function everyPair(): iterable
    {
        $allowed = array_map(static fn (array $pair): string => $pair[0]->value . '>' . $pair[1]->value, self::allowed());
        foreach (Status::cases() as $from) {
            foreach (Status::cases() as $to) {
                $key = $from->value . '>' . $to->value;
                yield $key => [$from, $to, in_array($key, $allowed, true)];
            }
        }
    }

    #[DataProvider('everyPair')]
    public function testOnlyListedTransitionsAreAllowed(Status $from, Status $to, bool $expected): void
    {
        self::assertSame($expected, (new BuyComplaintStateMachine())->canTransition($from, $to));
    }

    public function testApprovedIsFinal(): void
    {
        $this->expectException(InvalidStatusTransition::class);

        (new BuyComplaintStateMachine())->assertCanTransition(Status::Approved, Status::Recalled);
    }
}
