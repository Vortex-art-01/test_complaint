<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\ComplaintQueries;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\BuyComplaint\DecisionTypePolicy;

/**
 * ФЛК решения: у каждого лота жалобы есть вердикт и ответ со сформированным
 * PDF, у каждого лота решения есть тип решения, согласованный с вердиктами.
 */
final class DecisionCompleteness
{
    public function __construct(
        private readonly ComplaintQueries $queries,
        private readonly AppComplaintRepository $complaints,
        private readonly DecisionTypePolicy $policy,
    ) {
    }

    /**
     * @return list<DecisionGap>
     */
    public function gaps(int $buyComplaintId): array
    {
        $gaps = [];
        foreach ($this->queries->decisionLots($buyComplaintId) as $row) {
            $type = $row->lot->decisionType;
            $verdicts = array_values(array_filter(array_map(
                static fn (AppComplaintLot $lot) => $lot->verdict,
                $this->complaints->lotsOfBuyComplaintLot($row->lot->id),
            )));
            $mismatch = $type !== null && $row->isCovered() && !$this->policy->isAllowed($type, $verdicts);

            if (!$row->isCovered() || $type === null || $mismatch) {
                $gaps[] = new DecisionGap($row->buyLotNumber, $row->answeredCount, $row->complaintsCount, $type === null, $mismatch);
            }
        }

        return $gaps;
    }

    /**
     * @throws ValidationFailed
     */
    public function assertComplete(int $buyComplaintId): void
    {
        $gaps = $this->gaps($buyComplaintId);
        if ($gaps !== []) {
            throw ValidationFailed::field('form', 'complaints_error_decision_incomplete', [
                'lots' => implode(', ', array_map(
                    static fn (DecisionGap $gap): string => sprintf('%s (%d / %d)', $gap->buyLotNumber, $gap->covered, $gap->total),
                    $gaps,
                )),
            ]);
        }
    }
}
