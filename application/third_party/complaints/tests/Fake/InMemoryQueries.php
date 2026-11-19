<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Repository\ComplaintListRow;
use Complaints\Application\Repository\ComplaintQueries;
use Complaints\Application\Repository\DecisionListRow;
use Complaints\Application\Repository\DecisionLotRow;
use Complaints\Application\Shared\Page;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Document\DocumentType;

/**
 * Списки реестров, вычисленные по репозиториям в памяти.
 */
final class InMemoryQueries implements ComplaintQueries
{
    public function __construct(
        private readonly InMemoryAppComplaints $complaints,
        private readonly InMemoryBuyComplaints $decisions,
        private readonly InMemoryProcurement $procurement,
        private readonly InMemoryDocuments $documents,
    ) {
    }

    public function organizationComplaints(int $organizationId, int $page, int $pageSize): Page
    {
        $rows = [];
        foreach (array_reverse($this->complaints->complaints, true) as $row) {
            if ($row['supplier_org_id'] !== $organizationId || $row['deleted_at'] !== null) {
                continue;
            }
            $complaint = $this->complaints->get($row['id']);
            $lot = $this->complaints->lotOf($complaint->id);
            $buy = $this->procurement->buy($complaint->buyId);
            $rows[] = new ComplaintListRow(
                $complaint,
                $buy->number,
                $buy->name,
                $this->procurement->buyLot($lot->buyLotId)->number,
                $lot->verdict,
            );
        }

        return $this->page($rows, $page, $pageSize);
    }

    public function authorityDecisions(int $authorityOrgId, ?BuyComplaintStatus $status, ?string $buyNumber, int $page, int $pageSize): Page
    {
        return $this->decisionPage(
            fn (BuyComplaint $d): bool => $d->authorityOrgId === $authorityOrgId && ($status === null || $d->status === $status),
            $buyNumber,
            $page,
            $pageSize,
        );
    }

    public function organizerDecisions(int $organizerOrgId, ?ExecutionStatus $status, ?string $buyNumber, int $page, int $pageSize): Page
    {
        return $this->decisionPage(
            fn (BuyComplaint $d): bool => $d->status === BuyComplaintStatus::Approved
                && $this->procurement->buy($d->buyId)->organizerOrgId === $organizerOrgId
                && ($status === null || $d->executionStatus === $status),
            $buyNumber,
            $page,
            $pageSize,
        );
    }

    public function decisionLots(int $buyComplaintId): array
    {
        $rows = [];
        foreach ($this->decisions->lots($buyComplaintId) as $lot) {
            $complaintLots = $this->complaints->lotsOfBuyComplaintLot($lot->id);
            $answered = count(array_filter(
                $complaintLots,
                fn ($l): bool => $l->answerId !== null && $this->documents->current(DocumentType::Answer, $l->answerId) !== null,
            ));
            $buyLot = $this->procurement->buyLot($lot->buyLotId);
            $rows[] = new DecisionLotRow(
                $lot,
                $buyLot->number,
                $buyLot->name,
                count($complaintLots),
                $answered,
                count(array_filter($complaintLots, static fn ($l): bool => $l->verdict === Verdict::Satisfied)),
                count(array_filter($complaintLots, static fn ($l): bool => $l->verdict === Verdict::Rejected)),
            );
        }

        return $rows;
    }

    /**
     * @param callable(BuyComplaint): bool $condition
     */
    private function decisionPage(callable $condition, ?string $buyNumber, int $page, int $pageSize): Page
    {
        $rows = [];
        foreach (array_keys($this->decisions->decisions) as $id) {
            $decision = $this->decisions->get($id);
            $buy = $this->procurement->buy($decision->buyId);
            if (!$condition($decision) || ($buyNumber !== null && $buy->number !== $buyNumber)) {
                continue;
            }
            $lots = $this->decisions->lots($id);
            $rows[] = new DecisionListRow(
                $decision,
                $buy->number,
                $buy->name,
                $buy->organizerOrgId,
                count($this->complaints->ofBuyComplaint($id)),
                $this->distinctTypes($lots),
            );
        }

        return $this->page($rows, $page, $pageSize);
    }

    /**
     * @param list<\Complaints\Domain\BuyComplaint\BuyComplaintLot> $lots
     * @return list<\Complaints\Domain\BuyComplaint\DecisionType>
     */
    private function distinctTypes(array $lots): array
    {
        $types = [];
        foreach ($lots as $lot) {
            if ($lot->decisionType !== null && !in_array($lot->decisionType, $types, true)) {
                $types[] = $lot->decisionType;
            }
        }

        return $types;
    }

    /**
     * @template T
     * @param list<T> $rows
     * @return Page<T>
     */
    private function page(array $rows, int $page, int $pageSize): Page
    {
        return new Page(array_slice($rows, Page::offset($page, $pageSize), $pageSize), count($rows), max(1, $page), $pageSize);
    }
}
