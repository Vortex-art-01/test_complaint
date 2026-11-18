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
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Signature\SignatureStatus;
use Complaints\Domain\Signature\SignerRole;

/**
 * Реестры: один запрос на страницу и один на количество, без N+1.
 */
final class SqlComplaintQueries implements ComplaintQueries
{
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
            'SELECT count(*) FROM test_app_complaint c WHERE c.supplier_org_id = :org AND c.deleted_at IS NULL',
            ['org' => $organizationId],
        ));

        $rows = $this->db->rows(
            sprintf(
                'SELECT %s, %s, b.%s AS buy_number, b.%s AS buy_name_ru, b.%s AS buy_name_kz, pl.%s AS lot_number,'
                . ' (SELECT s.signed_at FROM test_complaint_documents doc'
                . '   JOIN test_complaint_signatures s ON s.test_complaint_documents_id = doc.id'
                . '  WHERE doc.test_app_complaint_id = c.id AND doc.is_current'
                . '    AND s.signer_role = :head_role AND s.status = :signed'
                . '  ORDER BY s.signed_at DESC LIMIT 1) AS head_signed_at'
                . ' FROM test_app_complaint c'
                . ' JOIN test_app_complaint_lots l ON l.test_app_complaint_id = c.id AND l.deleted_at IS NULL'
                . ' JOIN %s b ON b.id = c.buy_id'
                . ' JOIN %s pl ON pl.id = l.test_buy_lots_id'
                . ' WHERE c.supplier_org_id = :org AND c.deleted_at IS NULL'
                . ' ORDER BY c.created_at DESC, c.id DESC'
                . ' LIMIT :limit OFFSET :offset',
                AppComplaintMapper::COLUMNS,
                AppComplaintMapper::LOT_COLUMNS,
                $this->schema->buyNumber(),
                $this->schema->buyNameRu(),
                $this->schema->buyNameKz(),
                $this->schema->lotNumber(),
                $this->schema->buyTable(),
                $this->schema->lotTable(),
            ),
            [
                'head_role' => SignerRole::Head,
                'signed' => SignatureStatus::Signed,
                'org' => $organizationId,
                'limit' => $pageSize,
                'offset' => Page::offset($page, $pageSize),
            ],
        );

        $items = array_map(
            fn (array $row): ComplaintListRow => new ComplaintListRow(
                $this->complaints->complaint($row),
                (string) $row['buy_number'],
                self::text($row['buy_name_ru'], $row['buy_name_kz']),
                (string) $row['lot_number'],
                $this->values->momentOrNull($row['head_signed_at']),
                $this->complaints->lot($row)->verdict,
            ),
            $rows,
        );

        return new Page($items, $total, max(1, $page), $pageSize);
    }

    public function authorityDecisions(int $authorityOrgId, ?BuyComplaintStatus $status, ?string $buyNumber, int $page, int $pageSize): Page
    {
        $conditions = ['d.authority_org_id = :authority'];
        $params = ['authority' => $authorityOrgId];
        if ($status !== null) {
            $conditions[] = 'd.status = :status';
            $params['status'] = $status;
        }

        return $this->decisionPage($conditions, $params, $buyNumber, 'd.id DESC', $page, $pageSize);
    }

    public function organizerDecisions(int $organizerOrgId, ?ExecutionStatus $status, ?string $buyNumber, int $page, int $pageSize): Page
    {
        $conditions = ['d.status = :approved', sprintf('b.%s = :organizer', $this->schema->buyOrganizer())];
        $params = ['approved' => BuyComplaintStatus::Approved, 'organizer' => $organizerOrgId];
        if ($status !== null) {
            $conditions[] = 'd.execution_status = :execution_status';
            $params['execution_status'] = $status;
        }

        return $this->decisionPage($conditions, $params, $buyNumber, 'd.approved_at DESC, d.id DESC', $page, $pageSize);
    }

    public function decisionLots(int $buyComplaintId): array
    {
        $rows = $this->db->rows(
            sprintf(
                'SELECT %1$s, pl.%2$s AS buy_lot_number, pl.%3$s AS buy_lot_name_ru, pl.%4$s AS buy_lot_name_kz,'
                . ' count(acl.id) AS complaints_count,'
                . ' count(ad.id) AS answered_count,'
                . ' count(acl.id) FILTER (WHERE acl.verdict = :satisfied) AS satisfied_count,'
                . ' count(acl.id) FILTER (WHERE acl.verdict = :rejected) AS rejected_count'
                . ' FROM test_buy_complaint_lots bl'
                . ' JOIN %5$s pl ON pl.id = bl.test_buy_lots_id'
                . ' LEFT JOIN test_app_complaint_lots acl ON acl.test_buy_complaint_lots_id = bl.id AND acl.deleted_at IS NULL'
                . ' LEFT JOIN test_complaint_documents ad ON ad.test_complaint_answers_id = acl.test_complaint_answers_id AND ad.is_current'
                . ' WHERE bl.test_buy_complaint_id = :decision'
                . ' GROUP BY bl.id, pl.%2$s, pl.%3$s, pl.%4$s'
                . ' ORDER BY pl.%2$s, bl.id',
                BuyComplaintMapper::LOT_COLUMNS,
                $this->schema->lotNumber(),
                $this->schema->lotNameRu(),
                $this->schema->lotNameKz(),
                $this->schema->lotTable(),
            ),
            ['satisfied' => Verdict::Satisfied, 'rejected' => Verdict::Rejected, 'decision' => $buyComplaintId],
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
        if ($buyNumber !== null) {
            $conditions[] = sprintf('b.%s = :buy_number', $this->schema->buyNumber());
            $params['buy_number'] = $buyNumber;
        }
        $from = sprintf(' FROM test_buy_complaint d JOIN %s b ON b.id = d.buy_id WHERE ', $this->schema->buyTable()) . implode(' AND ', $conditions);

        $total = $this->values->int($this->db->value('SELECT count(*)' . $from, $params));
        $rows = $this->db->rows(
            sprintf(
                'SELECT %s, b.%s AS buy_number, b.%s AS buy_name_ru, b.%s AS buy_name_kz, b.%s AS organizer_org_id,'
                . ' (SELECT count(*) FROM test_app_complaint c WHERE c.test_buy_complaint_id = d.id AND c.deleted_at IS NULL) AS complaints_count,'
                . ' (SELECT string_agg(DISTINCT x.decision_type, \',\' ORDER BY x.decision_type) FROM test_buy_complaint_lots x'
                . '   WHERE x.test_buy_complaint_id = d.id AND x.decision_type IS NOT NULL) AS decision_types',
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
                $this->values->int($row['complaints_count']),
                $row['decision_types'] === null
                    ? []
                    : array_map(static fn (string $type): DecisionType => DecisionType::from($type), explode(',', (string) $row['decision_types'])),
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
