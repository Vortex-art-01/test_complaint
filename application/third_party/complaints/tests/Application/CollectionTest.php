<?php

declare(strict_types=1);

namespace Complaints\Tests\Application;

use Complaints\Application\AppComplaint\UseCase\WithdrawComplaint;
use Complaints\Application\Collection\CollectSubmittedComplaints;
use Complaints\Application\Port\NotificationType;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\History\HistoryEntityType;
use Complaints\Tests\Support\World;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
    private World $world;

    protected function setUp(): void
    {
        $this->world = new World();
    }

    public function testSubmittedComplaintsOfBuyGoToOneDecisionWithLotPerBuyLot(): void
    {
        $first = $this->world->submitted();
        $second = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $third = $this->world->submitted(appLotId: World::APP_LOT_B);

        $this->world->at('2026-10-20 00:10:00+05:00');
        $report = $this->world->get(CollectSubmittedComplaints::class)->execute();

        self::assertSame(1, $report->decisionsCreated);
        self::assertSame(3, $report->complaintsCollected);
        self::assertCount(1, $this->world->decisions->decisions);

        $decision = $this->world->decisions->get(1);
        self::assertSame(BuyComplaintStatus::InReview, $decision->status);
        self::assertSame(World::AUTHORITY, $decision->authorityOrgId);
        self::assertSame('2026-10-20 00:00', $decision->reviewPeriod->startsAt->format('Y-m-d H:i'));
        self::assertSame('2026-10-22 23:59', $decision->reviewPeriod->displayEndsAt()->format('Y-m-d H:i'));
        self::assertSame([World::LOT_A, World::LOT_B], array_map(static fn ($lot): int => $lot->buyLotId, $this->world->decisions->lots(1)));

        foreach ([$first, $second, $third] as $id) {
            $complaint = $this->world->complaints->get($id);
            self::assertSame(AppComplaintStatus::InReview, $complaint->status);
            self::assertSame(1, $complaint->buyComplaintId);
            self::assertNotNull($this->world->complaints->lotOf($id)->buyComplaintLotId);
        }
        self::assertSame(
            $this->world->complaints->lotOf($first)->buyComplaintLotId,
            $this->world->complaints->lotOf($second)->buyComplaintLotId,
        );
        self::assertSame([World::REVIEWER, World::SECOND_REVIEWER], $this->world->notifier->recipientsOf(NotificationType::ComplaintsReceived->value));
        self::assertSame(['in_review'], $this->world->history->trail(HistoryEntityType::BuyComplaint, 1));
    }

    public function testCollectionIsIdempotent(): void
    {
        $this->world->submitted();
        $this->world->collect();

        $report = $this->world->get(CollectSubmittedComplaints::class)->execute();

        self::assertSame(0, $report->decisionsCreated);
        self::assertSame(0, $report->complaintsCollected);
        self::assertCount(1, $this->world->decisions->decisions);
        self::assertCount(1, $this->world->decisions->lots);
    }

    public function testComplaintsWaitForGracePeriodAfterAppealEnds(): void
    {
        $id = $this->world->submitted();

        $this->world->at('2026-10-20 00:03:00+05:00');
        $report = $this->world->get(CollectSubmittedComplaints::class)->execute();

        self::assertSame(0, $report->complaintsCollected);
        self::assertSame(AppComplaintStatus::Submitted, $this->world->complaints->get($id)->status);
    }

    public function testOnlySubmittedComplaintsAreCollected(): void
    {
        $withdrawn = $this->world->submitted();
        $this->world->get(WithdrawComplaint::class)->execute($withdrawn);
        $draft = $this->world->draft(appLotId: World::APP_LOT_B);

        $this->world->collect();

        self::assertCount(0, $this->world->decisions->decisions);
        self::assertSame(AppComplaintStatus::Withdrawn, $this->world->complaints->get($withdrawn)->status);
        self::assertSame(AppComplaintStatus::Draft, $this->world->complaints->get($draft)->status);
    }
}
