<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\AppComplaint\ComplaintNumber;
use Complaints\Domain\BuyComplaint\Verdict;

/**
 * Строки test_app_complaint (псевдоним c) и test_app_complaint_lots (псевдоним l).
 */
final class AppComplaintMapper
{
    public const COLUMNS = 'c.id, c.number, c.buy_id, c.test_app_id, c.test_buy_complaint_id, c.supplier_org_id,'
        . ' c.author_user_id, c.head_user_id, c.contact_phone, c.description, c.requisites_snapshot,'
        . ' c.protocol_published_at, c.appeal_starts_at, c.appeal_ends_at, c.status, c.submitted_at,'
        . ' c.withdrawn_at, c.created_at, c.deleted_at';

    public const LOT_COLUMNS = 'l.id AS lot_id, l.test_app_complaint_id, l.test_app_id AS lot_app_id, l.test_app_lots_id,'
        . ' l.test_buy_lots_id, l.test_buy_complaint_lots_id, l.verdict, l.test_complaint_answers_id,'
        . ' l.reviewed_at, l.deleted_at AS lot_deleted_at';

    public function __construct(
        private readonly SqlValues $values,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function complaint(array $row): AppComplaint
    {
        return new AppComplaint(
            $this->values->int($row['id']),
            ComplaintNumber::fromString((string) $row['number']),
            $this->values->int($row['buy_id']),
            $this->values->int($row['test_app_id']),
            $this->values->intOrNull($row['test_buy_complaint_id']),
            $this->values->int($row['supplier_org_id']),
            $this->values->int($row['author_user_id']),
            $this->values->int($row['head_user_id']),
            $this->values->stringOrNull($row['contact_phone']),
            $this->values->stringOrNull($row['description']),
            $this->values->jsonOrNull($row['requisites_snapshot']),
            $this->values->moment($row['protocol_published_at']),
            $this->values->period($row['appeal_starts_at'], $row['appeal_ends_at']),
            AppComplaintStatus::from((string) $row['status']),
            $this->values->momentOrNull($row['submitted_at']),
            $this->values->momentOrNull($row['withdrawn_at']),
            $this->values->moment($row['created_at']),
            $this->values->momentOrNull($row['deleted_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function lot(array $row): AppComplaintLot
    {
        return new AppComplaintLot(
            $this->values->int($row['lot_id']),
            $this->values->int($row['test_app_complaint_id']),
            $this->values->int($row['lot_app_id']),
            $this->values->int($row['test_app_lots_id']),
            $this->values->int($row['test_buy_lots_id']),
            $this->values->intOrNull($row['test_buy_complaint_lots_id']),
            $row['verdict'] === null ? null : Verdict::from((string) $row['verdict']),
            $this->values->intOrNull($row['test_complaint_answers_id']),
            $this->values->momentOrNull($row['reviewed_at']),
            $this->values->momentOrNull($row['lot_deleted_at']),
        );
    }
}
