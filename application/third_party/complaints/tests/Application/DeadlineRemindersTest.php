<?php

declare(strict_types=1);

namespace Complaints\Tests\Application;

use Complaints\Application\Notification\SendDeadlineReminders;
use Complaints\Application\Port\NotificationType;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Tests\Support\ReviewSteps;
use Complaints\Tests\Support\World;
use PHPUnit\Framework\TestCase;

/**
 * Напоминания за сутки до конца срока: рассмотрение до 22.10 23:59, исполнение до 23.10 23:59.
 */
final class DeadlineRemindersTest extends TestCase
{
    private World $world;
    private ReviewSteps $steps;
    private int $complaint;

    protected function setUp(): void
    {
        $this->world = new World();
        $this->steps = new ReviewSteps($this->world);
        $this->complaint = $this->world->submitted();
        $this->world->collect();
    }

    private function remind(string $moment): int
    {
        $this->world->at($moment);

        return $this->world->get(SendDeadlineReminders::class)->execute();
    }

    public function testReviewReminderGoesToAllReviewersWhileDecisionIsFree(): void
    {
        self::assertSame(0, $this->remind('2026-10-21 23:30:00+05:00'));
        self::assertSame(2, $this->remind('2026-10-22 08:00:00+05:00'));
        self::assertSame(
            [World::REVIEWER, World::SECOND_REVIEWER],
            $this->world->notifier->recipientsOf(NotificationType::ReviewDeadline->value),
        );
    }

    public function testReviewReminderGoesToWhoeverHoldsTheDecision(): void
    {
        $decisionId = $this->steps->decisionOf($this->complaint);
        $this->steps->chooseMethod($decisionId);
        $this->steps->answerAll([$this->complaint => Verdict::Rejected]);
        $this->steps->send($decisionId);

        self::assertSame(1, $this->remind('2026-10-22 08:00:00+05:00'));
        self::assertSame([World::COORDINATOR], $this->world->notifier->recipientsOf(NotificationType::ReviewDeadline->value));
    }

    public function testExecutionReminderGoesToOrganizer(): void
    {
        $this->world->at('2026-10-21 14:21:00+05:00');
        $this->steps->decide([$this->complaint => Verdict::Satisfied], [World::LOT_A => DecisionType::CancelPurchase]);

        self::assertSame(0, $this->remind('2026-10-22 08:00:00+05:00'), 'Решение утверждено — о рассмотрении не напоминаем.');
        self::assertSame(1, $this->remind('2026-10-23 09:00:00+05:00'));
        self::assertSame(
            [World::ORGANIZER_ORG],
            $this->world->notifier->recipientsOf(NotificationType::ExecutionDeadline->value, 'organization'),
        );
    }
}
