<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Repository\ComplaintListRow;
use Complaints\Application\Repository\ComplaintQueries;
use Complaints\Application\Repository\DecisionListRow;
use Complaints\Application\Repository\DecisionLotRow;
use Complaints\Application\Shared\LocalizedText;
use Complaints\Application\Shared\Page;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;

/**
 * Реестры: один запрос на страницу и один на количество, без N+1.
 */
final class SqlComplaintQueries implements ComplaintQueries
{
    /** Лот жалобы с ответом, PDF которого действует (по способу ответа решения). */
    private const ANSWERED = 'acl.answer_id IS NOT NULL AND ('
        . '(tld.method_id = ' . ReferenceIds::METHOD_PER_COMPLAINT
        . ' AND EXISTS (SELECT 1 FROM app_complaint_docs ad WHERE ad.app_complaint_lots_id = acl.id'
        . ' AND ad.ref_app_complaint_docs_type_id = ' . ReferenceIds::APP_DOC_ANSWER . ' AND ad.deleted_at IS NULL))'
        . ' OR (tld.method_id = ' . ReferenceIds::METHOD_PER_LOT
        . ' AND EXISTS (SELECT 1 FROM trd_complaint_docs td WHERE td.trd_complaint_lots_id = tl.id'
        . ' AND td.ref_trd_complaint_docs_type_id = ' . ReferenceIds::TRD_DOC_LOT_ANSWER . ' AND td.deleted_at IS NULL)))';

    public function __construct(
        private readonly Database $db,
        private readonly ProjectSchema $schema,
        private readonly AppComplaintMapper $complaints,
        private readonly BuyComplaintMapper $decisions,
        private readonly SqlValues $values,
    ) {
    }

    public function organizationComplaints(int $organizationId, int $page, int $pageSize): Page
    {
        $total = $this->values->int($this->db->value(
            'SELECT count(*) FROM app_complaint c WHERE c.crsubject_id = :org AND c.deleted_at IS NULL',
            ['org' => $organizationId],
        ));

        $rows = $this->db->rows(
            sprintf(
                'SELECT %s, cb.%s AS buy_number, cb.%s AS buy_name_ru, cb.%s AS buy_name_kz,'
                . ' pl.%s AS lot_number, l.verdict_id AS lot_verdict_id'
                . ' FROM %s'
                . ' JOIN app_complaint_lots l ON l.app_complaint_id = c.id'
                . ' JOIN %s pl ON pl.id = l.trd_buy_lot_id'
                . ' WHERE c.crsubject_id = :org AND c.deleted_at IS NULL'
                . ' ORDER BY c.created_at DESC, c.id DESC'
                . ' LIMIT :limit OFFSET :offset',
                $this->complaints->columns(),
                $this->schema->buyNumber(),
                $this->schema->buyNameRu(),
                $this->schema->buyNameKz(),
                $this->schema->lotNumber(),
                $this->complaints->from(),
                $this->schema->lotTable(),
            ),
            ['org' => $organizationId, 'limit' => $pageSize, 'offset' => Page::offset($page, $pageSize)],
        );

        $items = array_map(
            function (array $row): ComplaintListRow {
                $verdict = $this->values->intOrNull($row['lot_verdict_id']);

                return new ComplaintListRow(
                    $this->complaints->complaint($row),
                    (string) $row['buy_number'],
                    self::text($row['buy_name_ru'], $row['buy_name_kz']),
                    (string) $row['lot_number'],
                    $verdict === null ? null : ReferenceIds::verdictOf($verdict),
                );
            },
            $rows,
        );

        return new Page($items, $total, max(1, $page), $pageSize);
    }

    public function authorityDecisions(int $authorityOrgId, ?BuyComplaintStatus $status, ?string $buyNumber, int $page, int $pageSize): Page
    {
        $conditions = ['d.crsubject_id = :authority'];
        if ($status !== null) {
            $conditions[] = 'd.status_id IN (' . implode(', ', ReferenceIds::decisionStatuses($status)) . ')';
        }

        return $this->decisionPage($conditions, ['authority' => $authorityOrgId], $buyNumber, 'd.id DESC', $page, $pageSize);
    }

    public function organizerDecisions(int $organizerOrgId, ?ExecutionStatus $status, ?string $buyNumber, int $page, int $pageSize): Page
    {
        $conditions = [
            $status === null ? 'd.status_id IN (' . ReferenceIds::DECISION_APPROVED . ')' : 'd.status_id = ' . ReferenceIds::approvedStatus($status),
            sprintf('b.%s = :organizer', $this->schema->buyOrganizer()),
        ];

        return $this->decisionPage($conditions, ['organizer' => $organizerOrgId], $buyNumber, 'd.approved_at DESC, d.id DESC', $page, $pageSize);
    }

    public function decisionLots(int $buyComplaintId): array
    {
        $rows = $this->db->rows(
            sprintf(
                'SELECT %1$s, pl.%2$s AS buy_lot_number, pl.%3$s AS buy_lot_name_ru, pl.%4$s AS buy_lot_name_kz,'
                . ' count(acl.id) AS complaints_count,'
                . ' count(acl.id) FILTER (WHERE %6$s) AS answered_count,'
                . ' count(acl.id) FILTER (WHERE acl.verdict_id = %7$d) AS satisfied_count,'
                . ' count(acl.id) FILTER (WHERE acl.verdict_id = %8$d) AS rejected_count'
                . ' FROM %9$s'
                . ' JOIN %5$s pl ON pl.id = tl.trd_buy_lot_id'
                . ' LEFT JOIN app_complaint_lots acl ON acl.trd_complaint_lots_id = tl.id'
                . ' AND EXISTS (SELECT 1 FROM app_complaint ac WHERE ac.id = acl.app_complaint_id AND ac.deleted_at IS NULL)'
                . ' WHERE tl.trd_complaint_id = :decision'
                . ' GROUP BY tl.id, tld.id, pl.%2$s, pl.%3$s, pl.%4$s'
                . ' ORDER BY pl.%2$s, tl.id',
                BuyComplaintMapper::LOT_COLUMNS,
                $this->schema->lotNumber(),
                $this->schema->lotNameRu(),
                $this->schema->lotNameKz(),
                $this->schema->lotTable(),
                self::ANSWERED,
                ReferenceIds::VERDICT_SATISFIED,
                ReferenceIds::VERDICT_REJECTED,
                BuyComplaintMapper::LOT_FROM,
            ),
            ['decision' => $buyComplaintId],
        );

        return array_map(
            fn (array $row): DecisionLotRow => new DecisionLotRow(
                $this->decisions->lot($row),
                (string) $row['buy_lot_number'],
                self::text($row['buy_lot_name_ru'], $row['buy_lot_name_kz']),
                $this->values->int($row['complaints_count']),
                $this->values->int($row['answered_count']),
                $this->values->int($row['satisfied_count']),
                $this->values->int($row['rejected_count']),
            ),
            $rows,
        );
    }

    /**
     * @param list<string>         $conditions
     * @param array<string, mixed> $params
     * @return Page<DecisionListRow>
     */
    private function decisionPage(array $conditions, array $params, ?string $buyNumber, string $order, int $page, int $pageSize): Page
    {
        $conditions[] = 'd.deleted_at IS NULL';
        if ($buyNumber !== null) {
            $conditions[] = sprintf('b.%s = :buy_number', $this->schema->buyNumber());
            $params['buy_number'] = $buyNumber;
        }
        $from = ' FROM ' . BuyComplaintMapper::FROM . sprintf(' JOIN %s b ON b.id = d.trd_buy_id WHERE ', $this->schema->buyTable())
            . implode(' AND ', $conditions);

        $total = $this->values->int($this->db->value('SELECT count(*)' . $from, $params));
        $rows = $this->db->rows(
            sprintf(
                'SELECT %s, d.app_complaint_count, b.%s AS buy_number, b.%s AS buy_name_ru, b.%s AS buy_name_kz,'
                . ' b.%s AS organizer_org_id,'
                . ' (SELECT string_agg(DISTINCT CAST(x.decision_id AS text), \',\' ORDER BY CAST(x.decision_id AS text))'
                . ' FROM trd_complaint_lots x WHERE x.trd_complaint_id = d.id AND x.decision_id IS NOT NULL) AS decision_types',
                BuyComplaintMapper::COLUMNS,
                $this->schema->buyNumber(),
                $this->schema->buyNameRu(),
                $this->schema->buyNameKz(),
                $this->schema->buyOrganizer(),
            ) . $from . ' ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset',
            $params + ['limit' => $pageSize, 'offset' => Page::offset($page, $pageSize)],
        );

        $items = array_map(
            fn (array $row): DecisionListRow => new DecisionListRow(
                $this->decisions->decision($row),
                (string) $row['buy_number'],
                self::text($row['buy_name_ru'], $row['buy_name_kz']),
                $this->values->int($row['organizer_org_id']),
                $this->values->int($row['app_complaint_count']),
                $row['decision_types'] === null
                    ? []
                    : array_map(
                        static fn (string $id): DecisionType => ReferenceIds::decisionTypeOf((int) $id),
                        explode(',', (string) $row['decision_types']),
                    ),
            ),
            $rows,
        );

        return new Page($items, $total, max(1, $page), $pageSize);
    }

    private static function text(mixed $ru, mixed $kz): LocalizedText
    {
        return new LocalizedText((string) $ru, (string) ($kz ?? $ru));
    }
}
