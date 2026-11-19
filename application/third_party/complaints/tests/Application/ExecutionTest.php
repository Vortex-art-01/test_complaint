<?php

declare(strict_types=1);

namespace Complaints\Tests\Application;

use Complaints\Application\BuyComplaint\DecisionAction;
use Complaints\Application\BuyComplaint\UseCase\ShowDecision;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Execution\UseCase\CancelDecisionLots;
use Complaints\Application\Execution\UseCase\CompleteLotRevisions;
use Complaints\Application\Execution\UseCase\ListOrganizerDecisions;
use Complaints\Application\Execution\UseCase\StartLotsRevision;
use Complaints\Application\Port\NotificationType;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Document\DocumentType;
use Complaints\Tests\Support\ReviewSteps;
use Complaints\Tests\Support\World;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Исполнение организатором: жалобы поставщика-1 по лотам А и Б.
 */
final class ExecutionTest extends TestCase
{
    private World $world;
    private ReviewSteps $steps;
    private int $onLotA;
    private int $onLotB;

    protected function setUp(): void
    {
        $this->world = new World();
        $this->steps = new ReviewSteps($this->world);
        $this->onLotA = $this->world->submitted();
        $this->onLotB = $this->world->submitted(appLotId: World::APP_LOT_B);
        $this->world->collect();
        $this->world->at('2026-10-21 14:21:00+05:00');
    }

    /**
     * Лот А — отмена закупки, лот Б — пересмотр итогов.
     */
    private function approveCancelAndRevision(): int
    {
        $decisionId = $this->steps->decide(
            [$this->onLotA => Verdict::Satisfied, $this->onLotB => Verdict::Satisfied],
            [World::LOT_A => DecisionType::CancelPurchase, World::LOT_B => DecisionType::ReviseResults],
        );
        $this->world->at('2026-10-22 10:00:00+05:00');
        $this->world->actAs(World::ORGANIZER);

        return $decisionId;
    }

    private function lotStatus(int $complaintId): ?LotExecutionStatus
    {
        return $this->world->decisions->lot($this->steps->decisionLotOf($complaintId))->executionStatus;
    }

    public function testOrganizerSeesApprovedDecisionWithExecutionActions(): void
    {
        $decisionId = $this->approveCancelAndRevision();

        $registry = $this->world->get(ListOrganizerDecisions::class)->execute(ExecutionStatus::InProgress, null, 1);
        self::assertSame(1, $registry->total);
        self::assertSame($decisionId, $registry->items[0]->row->decision->id);
        self::assertSame('На исполнении', $registry->items[0]->procurementStatusName);

        self::assertSame(
            [DecisionAction::CancelLots, DecisionAction::StartRevision],
            $this->world->get(ShowDecision::class)->execute($decisionId)->actions,
        );
    }

    public function testCancellationExecutesLotsAtOnce(): void
    {
        $decisionId = $this->approveCancelAndRevision();

        self::assertSame(1, $this->world->get(CancelDecisionLots::class)->execute($decisionId));

        $action = $this->world->gateway->cancelled[0];
        self::assertSame(World::LOT_A, $action->buyLotId);
        self::assertSame($decisionId, $action->decisionId);
        self::assertSame('2026-10-21 14:21', $action->decisionDate->format('Y-m-d H:i'));
        self::assertSame('Комитет внутреннего государственного аудита', $action->authorityName);
        self::assertSame($this->world->documents->current(DocumentType::ConsolidatedAnswer, $decisionId)?->fileId, $action->basisFileId);
        self::assertSame([$this->world->complaints->get($this->onLotA)->number->value], $action->complaintNumbers);
        self::assertSame(World::ORGANIZER, $action->actorUserId);
        self::assertSame(LotExecutionStatus::Executed, $this->lotStatus($this->onLotA));
        self::assertSame(World::ORGANIZER, $this->world->decisions->lot($this->steps->decisionLotOf($this->onLotA))->executionStartedByUserId);
        self::assertSame(ExecutionStatus::InProgress, $this->world->decisions->get($decisionId)->executionStatus);

        $this->expectExceptionObject(new StatusConflict('complaints_error_nothing_to_execute'));
        $this->world->get(CancelDecisionLots::class)->execute($decisionId);
    }

    public function testRevisionIsExecutedWhenProtocolIsRepublished(): void
    {
        $decisionId = $this->approveCancelAndRevision();
        self::assertSame(1, $this->world->get(StartLotsRevision::class)->execute($decisionId));
        self::assertSame(World::LOT_B, $this->world->gateway->revised[0]->buyLotId);
        self::assertSame(LotExecutionStatus::Started, $this->lotStatus($this->onLotB));
        $this->world->get(CancelDecisionLots::class)->execute($decisionId);

        $revisions = $this->world->get(CompleteLotRevisions::class);
        self::assertSame(0, $revisions->execute());

        $this->world->gateway->revisionsCompleted[World::LOT_B] = new DateTimeImmutable('2026-10-23 12:00:00+05:00');
        self::assertSame(1, $revisions->execute());
        self::assertSame(0, $revisions->execute());

        $decision = $this->world->decisions->get($decisionId);
        self::assertSame(LotExecutionStatus::Executed, $this->lotStatus($this->onLotB));
        self::assertSame(ExecutionStatus::Executed, $decision->executionStatus);
        self::assertSame('2026-10-23 12:00', $decision->executedAt?->format('Y-m-d H:i'));
        self::assertSame([World::ORGANIZER_ORG], $this->world->notifier->recipientsOf(NotificationType::DecisionExecuted->value, 'organization'));
        self::assertSame([], $this->world->get(ShowDecision::class)->execute($decisionId)->actions);
    }

    public function testOnlyOrganizerOfTheBuyExecutes(): void
    {
        $decisionId = $this->approveCancelAndRevision();

        $this->world->actAs(World::REVIEWER);
        try {
            $this->world->get(CancelDecisionLots::class)->execute($decisionId);
            self::fail('Рассматривающий не исполняет решение.');
        } catch (AccessDenied) {
        }

        $this->world->policy->organizers = [World::BUY => World::SUPPLIER2];
        $this->world->actAs(World::ORGANIZER);
        $this->expectException(AccessDenied::class);
        $this->world->get(StartLotsRevision::class)->execute($decisionId);
    }

    public function testDecisionWithoutExecutionNeedsNothingFromOrganizer(): void
    {
        $decisionId = $this->steps->decide([$this->onLotA => Verdict::Rejected, $this->onLotB => Verdict::Rejected]);
        $this->world->actAs(World::ORGANIZER);

        self::assertSame(ExecutionStatus::NotRequired, $this->world->decisions->get($decisionId)->executionStatus);
        self::assertNull($this->world->decisions->get($decisionId)->executionPeriod);
        self::assertSame([], $this->world->get(ShowDecision::class)->execute($decisionId)->actions);

        $this->expectException(StatusConflict::class);
        $this->world->get(CancelDecisionLots::class)->execute($decisionId);
    }
}
