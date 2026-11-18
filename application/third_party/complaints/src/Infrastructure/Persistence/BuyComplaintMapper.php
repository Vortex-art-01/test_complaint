<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\BuyComplaint\ReviewMethod;

/**
 * Строки test_buy_complaint (псевдоним d) и test_buy_complaint_lots (псевдоним bl).
 */
final class BuyComplaintMapper
{
    public const COLUMNS = 'd.id, d.buy_id, d.authority_org_id, d.protocol_published_at, d.appeal_starts_at,'
        . ' d.appeal_ends_at, d.review_starts_at, d.review_ends_at, d.status, d.review_method,'
        . ' d.reviewer_user_id, d.coordinator_user_id, d.approver_user_id, d.sent_for_approval_at,'
        . ' d.decision_number, d.approved_at, d.execution_status, d.execution_starts_at,'
        . ' d.execution_ends_at, d.executed_at';

    public const LOT_COLUMNS = 'bl.id AS lot_id, bl.test_buy_complaint_id, bl.test_buy_lots_id, bl.decision_type,'
        . ' bl.execution_status AS lot_execution_status, bl.execution_started_at,'
        . ' bl.executed_at AS lot_executed_at, bl.executed_by_user_id';

    public function __construct(
        private readonly SqlValues $values,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function decision(array $row): BuyComplaint
    {
        return new BuyComplaint(
            $this->values->int($row['id']),
            $this->values->int($row['buy_id']),
            $this->values->int($row['authority_org_id']),
            $this->values->moment($row['protocol_published_at']),
            $this->values->period($row['appeal_starts_at'], $row['appeal_ends_at']),
            $this->values->period($row['review_starts_at'], $row['review_ends_at']),
            BuyComplaintStatus::from((string) $row['status']),
            $row['review_method'] === null ? null : ReviewMethod::from((string) $row['review_method']),
            $this->values->intOrNull($row['reviewer_user_id']),
            $this->values->intOrNull($row['coordinator_user_id']),
            $this->values->intOrNull($row['approver_user_id']),
            $this->values->momentOrNull($row['sent_for_approval_at']),
            $this->values->stringOrNull($row['decision_number']),
            $this->values->momentOrNull($row['approved_at']),
            $row['execution_status'] === null ? null : ExecutionStatus::from((string) $row['execution_status']),
            $this->values->periodOrNull($row['execution_starts_at'], $row['execution_ends_at']),
            $this->values->momentOrNull($row['executed_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function lot(array $row): BuyComplaintLot
    {
        return new BuyComplaintLot(
            $this->values->int($row['lot_id']),
            $this->values->int($row['test_buy_complaint_id']),
            $this->values->int($row['test_buy_lots_id']),
            $row['decision_type'] === null ? null : DecisionType::from((string) $row['decision_type']),
            $row['lot_execution_status'] === null ? null : LotExecutionStatus::from((string) $row['lot_execution_status']),
            $this->values->momentOrNull($row['execution_started_at']),
            $this->values->momentOrNull($row['lot_executed_at']),
            $this->values->intOrNull($row['executed_by_user_id']),
        );
    }
}
