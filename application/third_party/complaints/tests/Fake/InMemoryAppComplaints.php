<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\ApplicationLotCard;
use Complaints\Application\Repository\CollectionBatch;
use Complaints\Application\Repository\NewAppComplaint;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\AppComplaint\ComplaintNumber;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Жалобы в памяти: повторяет условные UPDATE и правила выборок SQL-репозитория.
 */
final class InMemoryAppComplaints implements AppComplaintRepository
{
    /** @var array<int, array<string, mixed>> */
    public array $complaints = [];

    /** @var array<int, array<string, mixed>> */
    public array $lots = [];

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

    public function create(NewAppComplaint $complaint, DateTimeImmutable $now): int
    {
        $id = count($this->complaints) + 1;
        $this->complaints[$id] = [
            'id' => $id,
            'number' => sprintf('Ж-%06d', $id),
            'buy_id' => $complaint->buyId,
            'app_id' => $complaint->appId,
            'buy_complaint_id' => null,
            'supplier_org_id' => $complaint->supplierOrgId,
            'author_user_id' => $complaint->authorUserId,
            'head_user_id' => $complaint->headUserId,
            'contact_phone' => null,
            'description' => null,
            'snapshot' => null,
            'protocol_published_at' => $complaint->protocolPublishedAt,
            'appeal_period' => $complaint->appealPeriod,
            'status' => AppComplaintStatus::Draft,
            'submitted_at' => null,
            'withdrawn_at' => null,
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

    public function changeAppealBasis(int $complaintId, DateTimeImmutable $protocolPublishedAt, Period $appealPeriod, DateTimeImmutable $now): void
    {
        $this->complaints[$complaintId]['protocol_published_at'] = $protocolPublishedAt;
        $this->complaints[$complaintId]['appeal_period'] = $appealPeriod;
    }

    public function saveRequisitesSnapshot(int $complaintId, ?array $snapshot, DateTimeImmutable $now): void
    {
        $this->complaints[$complaintId]['snapshot'] = $snapshot === null ? null : json_decode((string) json_encode($snapshot), true);
    }

    public function transition(int $complaintId, AppComplaintStatus $from, AppComplaintStatus $to, DateTimeImmutable $now, array $changes = []): bool
    {
        $row = $this->complaints[$complaintId] ?? null;
        if ($row === null || $row['status'] !== $from || $row['deleted_at'] !== null) {
            return false;
        }

        $row['status'] = $to;
        foreach (['submitted_at', 'withdrawn_at', 'buy_complaint_id'] as $key) {
            if (array_key_exists($key, $changes)) {
                $row[$key] = $changes[$key];
            }
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

    public function hasActiveComplaint(int $appLotId, DateTimeImmutable $appealEndsAt, ?int $exceptComplaintId): bool
    {
        foreach ($this->lots as $lot) {
            $complaint = $this->complaints[$lot['complaint_id']];
            if ($lot['deleted_at'] === null
                && $lot['app_lot_id'] === $appLotId
                && $complaint['deleted_at'] === null
                && $complaint['id'] !== $exceptComplaintId
                && $complaint['appeal_period']->endsAt == $appealEndsAt) {
                return true;
            }
        }

        return false;
    }

    public function collectionBatches(DateTimeImmutable $appealEndedBy): array
    {
        $groups = [];
        foreach ($this->complaints as $row) {
            if ($row['status'] !== AppComplaintStatus::Submitted || $row['deleted_at'] !== null || $row['appeal_period']->endsAt > $appealEndedBy) {
                continue;
            }
            $key = $row['buy_id'] . '|' . $row['appeal_period']->endsAt->getTimestamp();
            $groups[$key][] = $row;
        }

        $batches = [];
        foreach ($groups as $rows) {
            $starts = min(array_map(static fn (array $r) => $r['appeal_period']->startsAt, $rows));
            $published = max(array_map(static fn (array $r) => $r['protocol_published_at'], $rows));
            $batches[] = new CollectionBatch($rows[0]['buy_id'], new Period($starts, $rows[0]['appeal_period']->endsAt), $published);
        }

        return $batches;
    }

    public function lockSubmittedOfBatch(int $buyId, DateTimeImmutable $appealEndsAt): array
    {
        return array_values(array_map(
            fn (array $row): AppComplaint => $this->complaint($row),
            array_filter(
                $this->complaints,
                static fn (array $row): bool => $row['status'] === AppComplaintStatus::Submitted
                    && $row['deleted_at'] === null
                    && $row['buy_id'] === $buyId
                    && $row['appeal_period']->endsAt == $appealEndsAt,
            ),
        ));
    }

    public function ofBuyComplaint(int $buyComplaintId): array
    {
        return array_values(array_map(
            fn (array $row): AppComplaint => $this->complaint($row),
            array_filter($this->complaints, static fn (array $row): bool => $row['buy_complaint_id'] === $buyComplaintId),
        ));
    }

    public function lotOf(int $complaintId): AppComplaintLot
    {
        foreach ($this->lots as $lot) {
            if ($lot['complaint_id'] === $complaintId && $lot['deleted_at'] === null) {
                return $this->lot($lot['id']);
            }
        }

        throw new NotFound();
    }

    public function lot(int $lotId): AppComplaintLot
    {
        $row = $this->lots[$lotId] ?? throw new NotFound();

        return new AppComplaintLot(
            $row['id'],
            $row['complaint_id'],
            $row['app_id'],
            $row['app_lot_id'],
            $row['buy_lot_id'],
            $row['buy_complaint_lot_id'],
            $row['verdict'],
            $row['answer_id'],
            $row['reviewed_at'],
            $row['deleted_at'],
        );
    }

    public function addLot(int $complaintId, ApplicationLotCard $appLot, DateTimeImmutable $now): int
    {
        $id = count($this->lots) + 1;
        $this->lots[$id] = [
            'id' => $id,
            'complaint_id' => $complaintId,
            'app_id' => $appLot->appId,
            'app_lot_id' => $appLot->id,
            'buy_lot_id' => $appLot->buyLotId,
            'buy_complaint_lot_id' => null,
            'verdict' => null,
            'answer_id' => null,
            'reviewed_at' => null,
            'deleted_at' => null,
        ];

        return $id;
    }

    public function removeLot(int $lotId, DateTimeImmutable $now): void
    {
        $this->lots[$lotId]['deleted_at'] = $now;
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

    public function markReviewed(int $buyComplaintId, DateTimeImmutable $now): void
    {
        foreach ($this->lotsOfBuyComplaint($buyComplaintId) as $lot) {
            $this->lots[$lot->id]['reviewed_at'] = $now;
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
            if ($lot['deleted_at'] === null && $condition($lot)) {
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
            ComplaintNumber::fromString($row['number']),
            $row['buy_id'],
            $row['app_id'],
            $row['buy_complaint_id'],
            $row['supplier_org_id'],
            $row['author_user_id'],
            $row['head_user_id'],
            $row['contact_phone'],
            $row['description'],
            $row['snapshot'],
            $row['protocol_published_at'],
            $row['appeal_period'],
            $row['status'],
            $row['submitted_at'],
            $row['withdrawn_at'],
            $row['created_at'],
            $row['deleted_at'],
        );
    }
}
