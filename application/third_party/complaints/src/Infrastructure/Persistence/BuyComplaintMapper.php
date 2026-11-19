<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;

/**
 * Строки trd_complaint и trd_complaint_lots.
 *
 * Псевдонимы решения: d — trd_complaint, ap, rv, ex — сроки обжалования,
 * рассмотрения и исполнения. Участники маршрута — из trd_complaint_member,
 * дата исполнения — самая поздняя дата исполнения лота.
 *
 * Псевдонимы лота: tl — trd_complaint_lots, tld — его решение. Статус
 * исполнения лота следует из статуса решения, типа решения по лоту и дат.
 */
final class BuyComplaintMapper
{
    public const COLUMNS = 'd.id, d.trd_buy_id, d.crsubject_id, d.status_id, d.method_id, d.sent_for_approval_at,'
        . ' d.sent_by_user_id, d.coordinated_at, d.approved_at, d.recalled_at, d.recalled_by_user_id,'
        . ' ap.start_at AS appeal_start_at, ap.end_at AS appeal_end_at,'
        . ' rv.start_at AS review_start_at, rv.end_at AS review_end_at,'
        . ' ex.start_at AS execution_start_at, ex.end_at AS execution_end_at,'
        . ' (SELECT m.member_id FROM trd_complaint_member m WHERE m.trd_complaint_id = d.id'
        . ' AND m.ref_trd_complaint_role_id = ' . ReferenceIds::ROLE_REVIEWER . ') AS reviewer_user_id,'
        . ' (SELECT m.member_id FROM trd_complaint_member m WHERE m.trd_complaint_id = d.id'
        . ' AND m.ref_trd_complaint_role_id = ' . ReferenceIds::ROLE_COORDINATOR . ') AS coordinator_user_id,'
        . ' (SELECT m.member_id FROM trd_complaint_member m WHERE m.trd_complaint_id = d.id'
        . ' AND m.ref_trd_complaint_role_id = ' . ReferenceIds::ROLE_APPROVER . ') AS approver_user_id,'
        . ' CASE WHEN d.status_id = ' . ReferenceIds::DECISION_APPROVED_EXECUTED
        . ' THEN (SELECT max(x.executed_at) FROM trd_complaint_lots x WHERE x.trd_complaint_id = d.id) END AS executed_at';

    public const FROM = 'trd_complaint d'
        . ' JOIN trd_complaint_date ap ON ap.trd_complaint_id = d.id AND ap.date_type_id = ' . ReferenceIds::DATE_APPEAL
        . ' JOIN trd_complaint_date rv ON rv.trd_complaint_id = d.id AND rv.date_type_id = ' . ReferenceIds::DATE_REVIEW
        . ' LEFT JOIN trd_complaint_date ex ON ex.trd_complaint_id = d.id AND ex.date_type_id = ' . ReferenceIds::DATE_EXECUTION;

    public const LOT_COLUMNS = 'tl.id AS lot_id, tl.trd_complaint_id, tl.trd_buy_lot_id, tl.decision_id,'
        . ' tl.execution_started_at, tl.execution_started_by_user_id, tl.executed_at AS lot_executed_at,'
        . ' tld.status_id AS lot_decision_status_id';

    public const LOT_FROM = 'trd_complaint_lots tl JOIN trd_complaint tld ON tld.id = tl.trd_complaint_id';

    public function __construct(
        private readonly SqlValues $values,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function decision(array $row): BuyComplaint
    {
        $statusId = $this->values->int($row['status_id']);
        $method = $this->values->intOrNull($row['method_id']);

        return new BuyComplaint(
            $this->values->int($row['id']),
            $this->values->int($row['trd_buy_id']),
            $this->values->int($row['crsubject_id']),
            $this->values->period($row['appeal_start_at'], $row['appeal_end_at']),
            $this->values->period($row['review_start_at'], $row['review_end_at']),
            ReferenceIds::decisionStatusOf($statusId),
            $method === null ? null : ReferenceIds::reviewMethodOf($method),
            $this->values->intOrNull($row['reviewer_user_id']),
            $this->values->intOrNull($row['coordinator_user_id']),
            $this->values->intOrNull($row['approver_user_id']),
            $this->values->momentOrNull($row['sent_for_approval_at']),
            $this->values->intOrNull($row['sent_by_user_id']),
            $this->values->momentOrNull($row['coordinated_at']),
            $this->values->momentOrNull($row['approved_at']),
            $this->values->momentOrNull($row['recalled_at']),
            $this->values->intOrNull($row['recalled_by_user_id']),
            ReferenceIds::executionStatusOf($statusId),
            $this->values->periodOrNull($row['execution_start_at'], $row['execution_end_at']),
            $this->values->momentOrNull($row['executed_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function lot(array $row): BuyComplaintLot
    {
        $typeId = $this->values->intOrNull($row['decision_id']);
        $type = $typeId === null ? null : ReferenceIds::decisionTypeOf($typeId);
        $startedAt = $this->values->momentOrNull($row['execution_started_at']);
        $executedAt = $this->values->momentOrNull($row['lot_executed_at']);

        $execution = null;
        if (ReferenceIds::isApproved($this->values->int($row['lot_decision_status_id'])) && $type?->requiresExecution() === true) {
            $execution = match (true) {
                $executedAt !== null => LotExecutionStatus::Executed,
                $startedAt !== null => LotExecutionStatus::Started,
                default => LotExecutionStatus::Pending,
            };
        }

        return new BuyComplaintLot(
            $this->values->int($row['lot_id']),
            $this->values->int($row['trd_complaint_id']),
            $this->values->int($row['trd_buy_lot_id']),
            $type,
            $execution,
            $startedAt,
            $this->values->intOrNull($row['execution_started_by_user_id']),
            $executedAt,
        );
    }
}
