<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\AppComplaintDraft;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\ApplicationLotCard;
use Complaints\Application\Repository\CollectionBatch;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Shared\Period;
use DateTimeImmutable;
use LogicException;

/**
 * Жалобы в памяти. Срок обжалования жалобы — срок закупки, а после сбора —
 * срок, сохранённый в решении, как в SQL-репозитории.
 */
final class InMemoryAppComplaints implements AppComplaintRepository
{
    private const TRANSITION_CHANGES = ['submitted_at', 'submitted_by_user_id', 'buy_complaint_id'];

    /** @var array<int, array<string, mixed>> */
    public array $complaints = [];

    /** @var array<int, array<string, mixed>> */
    public array $lots = [];

    public function __construct(
        private readonly InMemoryProcurement $procurement,
        private readonly InMemoryBuyComplaints $decisions,
    ) {
    }

    public function get(int $complaintId): AppComplaint
    {
        $row = $this->complaints[$complaintId] ?? null;
        if ($row === null || $row['deleted_at'] !== null) {
            throw new NotFound();
        }

        return $this->complaint($row);
    }

    public function lock(int $complaintId): AppComplaint
    {
        return $this->get($complaintId);
    }

    public function create(AppComplaintDraft $complaint, DateTimeImmutable $now): int
    {
        $id = count($this->complaints) + 1;
        $this->complaints[$id] = [
            'id' => $id,
            'buy_id' => $complaint->buyId,
            'app_id' => $complaint->appId,
            'buy_complaint_id' => null,
            'supplier_org_id' => $complaint->supplierOrgId,
            'author_user_id' => $complaint->authorUserId,
            'head_user_id' => $complaint->headUserId,
            'contact_phone' => null,
            'description' => null,
            'status' => AppComplaintStatus::Draft,
            'submitted_at' => null,
            'submitted_by_user_id' => null,
            'created_at' => $now,
            'deleted_at' => null,
        ];

        return $id;
    }

    public function updateContent(int $complaintId, ?string $contactPhone, ?string $description, DateTimeImmutable $now): void
    {
        $this->complaints[$complaintId]['contact_phone'] = $contactPhone;
        $this->complaints[$complaintId]['description'] = $description;
    }

    public function changeHead(int $complaintId, int $headUserId, DateTimeImmutable $now): void
    {
        $this->complaints[$complaintId]['head_user_id'] = $headUserId;
    }

    public function transition(int $complaintId, AppComplaintStatus $from, AppComplaintStatus $to, DateTimeImmutable $now, array $changes = []): bool
    {
        $row = $this->complaints[$complaintId] ?? null;
        if ($row === null || $row['status'] !== $from || $row['deleted_at'] !== null) {
            return false;
        }

        $row['status'] = $to;
        foreach ($changes as $key => $value) {
            if (!in_array($key, self::TRANSITION_CHANGES, true)) {
                throw new LogicException('Неизвестное поле перехода жалобы: ' . $key);
            }
            $row[$key] = $value;
        }
        $this->complaints[$complaintId] = $row;

        return true;
    }

    public function softDelete(int $complaintId, DateTimeImmutable $now): bool
    {
        $row = $this->complaints[$complaintId];
        if ($row['deleted_at'] !== null || !$row['status']->isDeletable()) {
            return false;
        }
        $this->complaints[$complaintId]['deleted_at'] = $now;

        return true;
    }

    public function lockApplicationLot(int $appLotId): void
    {
    }

    public function hasActiveComplaint(int $appLotId, ?int $exceptComplaintId): bool
    {
        foreach ($this->lots as $lot) {
            $complaint = $this->complaints[$lot['complaint_id']];
            if ($lot['app_lot_id'] === $appLotId
                && $complaint['deleted_at'] === null
                && $complaint['buy_complaint_id'] === null
                && $complaint['id'] !== $exceptComplaintId) {
                return true;
            }
        }

        return false;
    }

    public function collectionBatches(DateTimeImmutable $appealEndedBy): array
    {
        $batches = [];
        foreach ($this->complaints as $row) {
            if ($row['status'] !== AppComplaintStatus::Submitted || $row['deleted_at'] !== null || $row['buy_complaint_id'] !== null) {
                continue;
            }
            $period = $this->procurement->buy($row['buy_id'])->appealPeriod;
            if ($period !== null && $period->endsAt <= $appealEndedBy) {
                $batches[$row['buy_id']] = new CollectionBatch($row['buy_id'], $period);
            }
        }

        return array_values($batches);
    }

    public function lockSubmittedOfBuy(int $buyId): array
    {
        return array_values(array_map(
            fn (array $row): AppComplaint => $this->complaint($row),
            array_filter(
                $this->complaints,
                static fn (array $row): bool => $row['status'] === AppComplaintStatus::Submitted
                    && $row['deleted_at'] === null
                    && $row['buy_complaint_id'] === null
                    && $row['buy_id'] === $buyId,
            ),
        ));
    }

    public function ofBuyComplaint(int $buyComplaintId): array
    {
        return array_values(array_map(
            fn (array $row): AppComplaint => $this->complaint($row),
            array_filter(
                $this->complaints,
                static fn (array $row): bool => $row['buy_complaint_id'] === $buyComplaintId && $row['deleted_at'] === null,
            ),
        ));
    }

    public function lotOf(int $complaintId): AppComplaintLot
    {
        foreach ($this->lots as $lot) {
            if ($lot['complaint_id'] === $complaintId) {
                return $this->lot($lot['id']);
            }
        }

        throw new NotFound();
    }

    public function lot(int $lotId): AppComplaintLot
    {
        $row = $this->lots[$lotId] ?? throw new NotFound();
        $complaint = $this->complaints[$row['complaint_id']];

        return new AppComplaintLot(
            $row['id'],
            $row['complaint_id'],
            $complaint['app_id'],
            $row['app_lot_id'],
            $row['buy_lot_id'],
            $row['buy_complaint_lot_id'],
            $row['verdict'],
            $row['answer_id'],
            $complaint['status'] === AppComplaintStatus::Reviewed && $complaint['buy_complaint_id'] !== null
                ? $this->decisions->get($complaint['buy_complaint_id'])->approvedAt
                : null,
        );
    }

    public function addLot(int $complaintId, ApplicationLotCard $appLot, DateTimeImmutable $now): int
    {
        $id = count($this->lots) + 1;
        $this->lots[$id] = [
            'id' => $id,
            'complaint_id' => $complaintId,
            'app_lot_id' => $appLot->id,
            'buy_lot_id' => $appLot->buyLotId,
            'buy_complaint_lot_id' => null,
            'verdict' => null,
            'answer_id' => null,
        ];

        return $id;
    }

    public function changeLot(int $lotId, ApplicationLotCard $appLot, DateTimeImmutable $now): void
    {
        $this->lots[$lotId]['app_lot_id'] = $appLot->id;
        $this->lots[$lotId]['buy_lot_id'] = $appLot->buyLotId;
    }

    public function lotsOfBuyComplaintLot(int $buyComplaintLotId): array
    {
        return $this->lotsWhere(static fn (array $lot): bool => $lot['buy_complaint_lot_id'] === $buyComplaintLotId);
    }

    public function lotsOfBuyComplaint(int $buyComplaintId): array
    {
        return $this->lotsWhere(fn (array $lot): bool => $this->complaints[$lot['complaint_id']]['buy_complaint_id'] === $buyComplaintId);
    }

    public function linkToBuyComplaintLot(int $lotId, int $buyComplaintLotId, DateTimeImmutable $now): void
    {
        $this->lots[$lotId]['buy_complaint_lot_id'] = $buyComplaintLotId;
    }

    public function setVerdict(int $lotId, ?Verdict $verdict, DateTimeImmutable $now): void
    {
        $this->lots[$lotId]['verdict'] = $verdict;
    }

    public function setAnswer(int $lotId, ?int $answerId, DateTimeImmutable $now): void
    {
        $this->lots[$lotId]['answer_id'] = $answerId;
    }

    public function clearAnswers(int $buyComplaintId, DateTimeImmutable $now): void
    {
        foreach ($this->lotsOfBuyComplaint($buyComplaintId) as $lot) {
            $this->lots[$lot->id]['answer_id'] = null;
        }
    }

    /**
     * @param callable(array<string, mixed>): bool $condition
     * @return list<AppComplaintLot>
     */
    private function lotsWhere(callable $condition): array
    {
        $found = [];
        foreach ($this->lots as $lot) {
            if ($this->complaints[$lot['complaint_id']]['deleted_at'] === null && $condition($lot)) {
                $found[] = $this->lot($lot['id']);
            }
        }

        return $found;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function complaint(array $row): AppComplaint
    {
        return new AppComplaint(
            $row['id'],
            $row['buy_id'],
            $row['app_id'],
            $row['buy_complaint_id'],
            $row['supplier_org_id'],
            $row['author_user_id'],
            $row['head_user_id'],
            $row['contact_phone'],
            $row['description'],
            $this->appealPeriod($row),
            $row['status'],
            $row['submitted_at'],
            $row['submitted_by_user_id'],
            $row['created_at'],
            $row['deleted_at'],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function appealPeriod(array $row): Period
    {
        if ($row['buy_complaint_id'] !== null) {
            return $this->decisions->get($row['buy_complaint_id'])->appealPeriod;
        }

        return $this->procurement->buy($row['buy_id'])->appealPeriod
            ?? throw new LogicException('У закупки нет срока обжалования.');
    }
}
