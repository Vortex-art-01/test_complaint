<?php

declare(strict_types=1);

namespace Complaints\Tests\Domain;

use Complaints\Domain\AppComplaint\AppComplaintStateMachine;
use Complaints\Domain\AppComplaint\AppComplaintStatus as Status;
use Complaints\Domain\Shared\InvalidStatusTransition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppComplaintStateMachineTest extends TestCase
{
    /**
     * @return list<array{Status, Status}>
     */
    private static function allowed(): array
    {
        return [
            [Status::Draft, Status::AwaitingSignature],
            [Status::AwaitingSignature, Status::Signed],
            [Status::AwaitingSignature, Status::Draft],
            [Status::Signed, Status::Submitted],
            [Status::Signed, Status::Draft],
            [Status::Submitted, Status::Withdrawn],
            [Status::Submitted, Status::InReview],
            [Status::Withdrawn, Status::Submitted],
            [Status::Withdrawn, Status::Draft],
            [Status::InReview, Status::Reviewed],
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
        self::assertSame($expected, (new AppComplaintStateMachine())->canTransition($from, $to));
    }

    public function testAssertThrowsOnForbiddenTransition(): void
    {
        $this->expectException(InvalidStatusTransition::class);

        (new AppComplaintStateMachine())->assertCanTransition(Status::Draft, Status::Submitted);
    }

    public function testDeletableOnlyBeforeSubmission(): void
    {
        $deletable = array_values(array_filter(Status::cases(), static fn (Status $s): bool => $s->isDeletable()));

        self::assertSame([Status::Draft, Status::AwaitingSignature, Status::Signed], $deletable);
    }
}
