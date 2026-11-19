<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\Shared\Period;
use UnexpectedValueException;

/**
 * Строки app_complaint и app_complaint_lots.
 *
 * Псевдонимы жалобы: c — app_complaint, cb — закупка, cap — срок обжалования
 * решения. Срок обжалования собранной жалобы — срок её решения, остальных —
 * текущий срок закупки.
 *
 * Псевдонимы лота: l — app_complaint_lots, lc — его жалоба, ld — решение жалобы.
 */
final class AppComplaintMapper
{
    public const LOT_COLUMNS = 'l.id AS lot_id, l.app_complaint_id, lc.trd_app_id AS lot_app_id, l.trd_app_lot_id,'
        . ' l.trd_buy_lot_id, l.trd_complaint_lots_id, l.verdict_id, l.answer_id,'
        . ' CASE WHEN lc.status_id = ' . ReferenceIds::COMPLAINT_REVIEWED . ' THEN ld.approved_at END AS lot_reviewed_at';

    public const LOT_FROM = 'app_complaint_lots l'
        . ' JOIN app_complaint lc ON lc.id = l.app_complaint_id'
        . ' LEFT JOIN trd_complaint ld ON ld.id = lc.trd_complaint_id';

    public function __construct(
        private readonly SqlValues $values,
        private readonly ProjectSchema $schema,
    ) {
    }

    public function columns(): string
    {
        return sprintf(
            'c.id, c.trd_complaint_id, c.trd_buy_id, c.trd_app_id, c.director_id, c.status_id, c.phone_number,'
            . ' c.description, c.date_apply, c.applyed_by_user_id, c.cruser_id, c.crsubject_id, c.created_at,'
            . ' c.deleted_at, cap.start_at AS decision_appeal_start_at, cap.end_at AS decision_appeal_end_at,'
            . ' CAST(CAST(cb.%s AS date) AS text) AS buy_appeal_start, CAST(CAST(cb.%s AS date) AS text) AS buy_appeal_end',
            $this->schema->buyAppealStart(),
            $this->schema->buyAppealEnd(),
        );
    }

    /**
     * FROM для жалобы: app_complaint c с закупкой и сроком обжалования решения.
     */
    public function from(): string
    {
        return sprintf(
            'app_complaint c JOIN %s cb ON cb.id = c.trd_buy_id'
            . ' LEFT JOIN trd_complaint_date cap ON cap.trd_complaint_id = c.trd_complaint_id AND cap.date_type_id = %d',
            $this->schema->buyTable(),
            ReferenceIds::DATE_APPEAL,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function complaint(array $row): AppComplaint
    {
        return new AppComplaint(
            $this->values->int($row['id']),
            $this->values->int($row['trd_buy_id']),
            $this->values->int($row['trd_app_id']),
            $this->values->intOrNull($row['trd_complaint_id']),
            $this->values->int($row['crsubject_id']),
            $this->values->int($row['cruser_id']),
            $this->values->int($row['director_id']),
            $this->values->stringOrNull($row['phone_number']),
            $this->values->stringOrNull($row['description']),
            $this->appealPeriod($row),
            ReferenceIds::complaintStatusOf($this->values->int($row['status_id'])),
            $this->values->momentOrNull($row['date_apply']),
            $this->values->intOrNull($row['applyed_by_user_id']),
            $this->values->moment($row['created_at']),
            $this->values->momentOrNull($row['deleted_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function lot(array $row): AppComplaintLot
    {
        $verdict = $this->values->intOrNull($row['verdict_id']);

        return new AppComplaintLot(
            $this->values->int($row['lot_id']),
            $this->values->int($row['app_complaint_id']),
            $this->values->int($row['lot_app_id']),
            $this->values->int($row['trd_app_lot_id']),
            $this->values->int($row['trd_buy_lot_id']),
            $this->values->intOrNull($row['trd_complaint_lots_id']),
            $verdict === null ? null : ReferenceIds::verdictOf($verdict),
            $this->values->intOrNull($row['answer_id']),
            $this->values->momentOrNull($row['lot_reviewed_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function appealPeriod(array $row): Period
    {
        return $this->values->periodOrNull($row['decision_appeal_start_at'], $row['decision_appeal_end_at'])
            ?? $this->values->daysOrNull($row['buy_appeal_start'], $row['buy_appeal_end'])
            ?? throw new UnexpectedValueException(sprintf(
                'У закупки %d жалобы %d не установлен срок обжалования (%s.%s, %s).',
                $this->values->int($row['trd_buy_id']),
                $this->values->int($row['id']),
                $this->schema->buyTable(),
                $this->schema->buyAppealStart(),
                $this->schema->buyAppealEnd(),
            ));
    }
}
