<?php

declare(strict_types=1);

namespace Complaints\Tests\Domain;

use Complaints\Domain\AppComplaint\ComplaintNumber;
use Complaints\Domain\BuyComplaint\DecisionNumber;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\DecisionTypePolicy;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Shared\Sha256;
use Complaints\Domain\Signature\SignerRole;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DecisionRulesTest extends TestCase
{
    public function testAllRejectedAllowsOnlyNoAction(): void
    {
        $policy = new DecisionTypePolicy();
        $verdicts = [Verdict::Rejected, Verdict::Rejected];

        self::assertSame([DecisionType::NoAction], $policy->allowedTypes($verdicts));
        self::assertSame(DecisionType::NoAction, $policy->forcedType($verdicts));
        self::assertFalse($policy->isAllowed(DecisionType::CancelPurchase, $verdicts));
    }

    public function testAnySatisfiedAllowsEveryType(): void
    {
        $policy = new DecisionTypePolicy();
        $verdicts = [Verdict::Rejected, Verdict::Satisfied];

        self::assertSame(DecisionType::cases(), $policy->allowedTypes($verdicts));
        self::assertNull($policy->forcedType($verdicts));
        self::assertTrue($policy->isAllowed(DecisionType::NoAction, $verdicts));
    }

    public function testExecutionIsNotRequiredWhenAllLotsHaveNoAction(): void
    {
        self::assertSame(
            ExecutionStatus::NotRequired,
            ExecutionStatus::afterApproval([DecisionType::NoAction, DecisionType::NoAction]),
        );
        self::assertSame(
            ExecutionStatus::InProgress,
            ExecutionStatus::afterApproval([DecisionType::NoAction, DecisionType::ReviseResults]),
        );
    }

    public function testLotAwaitsExecutionOnlyForActionDecisions(): void
    {
        self::assertSame(LotExecutionStatus::Pending, LotExecutionStatus::afterApproval(DecisionType::CancelPurchase));
        self::assertSame(LotExecutionStatus::Pending, LotExecutionStatus::afterApproval(DecisionType::ReviseResults));
        self::assertNull(LotExecutionStatus::afterApproval(DecisionType::NoAction));
    }

    public function testDecisionNumberFormat(): void
    {
        self::assertSame('Р-125407-1', DecisionNumber::forCycle('125407-1', 1)->value);
        self::assertSame('Р-125407-1/2', DecisionNumber::forCycle(' 125407-1 ', 2)->value);
    }

    public function testDecisionNumberRejectsZeroCycle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecisionNumber::forCycle('125407-1', 0);
    }

    public function testComplaintNumberFormat(): void
    {
        self::assertSame('Ж-000221', ComplaintNumber::fromString('Ж-000221')->value);

        $this->expectException(InvalidArgumentException::class);
        ComplaintNumber::fromString('Ж-12');
    }

    public function testSha256OfBytes(): void
    {
        $hash = Sha256::ofBytes('abc');

        self::assertSame('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad', $hash->value);
        self::assertTrue($hash->equals(Sha256::fromHex($hash->value)));
    }

    public function testSignOrderOfConsolidatedRoute(): void
    {
        self::assertSame(
            [1, 2, 3],
            [SignerRole::Reviewer->signOrder(), SignerRole::Coordinator->signOrder(), SignerRole::Approver->signOrder()],
        );
    }
}
