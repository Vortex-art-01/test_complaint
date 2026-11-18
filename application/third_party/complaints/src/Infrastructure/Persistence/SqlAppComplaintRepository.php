<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

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

final class SqlAppComplaintRepository implements AppComplaintRepository
{
    private const TRANSITION_COLUMNS = [
        'submitted_at' => 'submitted_at',
        'withdrawn_at' => 'withdrawn_at',
        'buy_complaint_id' => 'test_buy_complaint_id',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly AppComplaintMapper $mapper,
        private readonly SqlValues $values,
    ) {
    }

    public function get(int $complaintId): AppComplaint
    {
        return $this->one('WHERE c.id = :id AND c.deleted_at IS NULL', ['id' => $complaintId]);
    }

    public function lock(int $complaintId): AppComplaint
    {
        return $this->one('WHERE c.id = :id AND c.deleted_at IS NULL FOR UPDATE', ['id' => $complaintId]);
    }

    public function create(AppComplaintDraft $complaint, DateTimeImmutable $now): int
    {
        return $this->values->int($this->db->value(
            'INSERT INTO test_app_complaint (buy_id, test_app_id, supplier_org_id, author_user_id, head_user_id,'
            . ' protocol_published_at, appeal_starts_at, appeal_ends_at, status, created_at, updated_at)'
            . ' VALUES (:buy, :app, :supplier, :author, :head, :protocol, :appeal_starts, :appeal_ends, :status, :created, :updated)'
            . ' RETURNING id',
            [
                'buy' => $complaint->buyId,
                'app' => $complaint->appId,
                'supplier' => $complaint->supplierOrgId,
                'author' => $complaint->authorUserId,
                'head' => $complaint->headUserId,
                'protocol' => $complaint->protocolPublishedAt,
                'appeal_starts' => $complaint->appealPeriod->startsAt,
                'appeal_ends' => $complaint->appealPeriod->endsAt,
                'status' => AppComplaintStatus::Draft,
                'created' => $now,
                'updated' => $now,
            ],
        ));
    }

    public function updateContent(int $complaintId, ?string $contactPhone, ?string $description, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_app_complaint SET contact_phone = :phone, description = :description, updated_at = :now WHERE id = :id',
            ['phone' => $contactPhone, 'description' => $description, 'now' => $now, 'id' => $complaintId],
        );
    }

    public function changeHead(int $complaintId, int $headUserId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_app_complaint SET head_user_id = :head, updated_at = :now WHERE id = :id',
            ['head' => $headUserId, 'now' => $now, 'id' => $complaintId],
        );
    }

    public function changeAppealBasis(int $complaintId, DateTimeImmutable $protocolPublishedAt, Period $appealPeriod, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_app_complaint SET protocol_published_at = :protocol, appeal_starts_at = :starts,'
            . ' appeal_ends_at = :ends, updated_at = :now WHERE id = :id',
            [
                'protocol' => $protocolPublishedAt,
                'starts' => $appealPeriod->startsAt,
                'ends' => $appealPeriod->endsAt,
                'now' => $now,
                'id' => $complaintId,
            ],
        );
    }

    public function saveRequisitesSnapshot(int $complaintId, ?array $snapshot, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_app_complaint SET requisites_snapshot = CAST(:snapshot AS jsonb), updated_at = :now WHERE id = :id',
            [
                'snapshot' => $snapshot === null ? null : json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'now' => $now,
                'id' => $complaintId,
            ],
        );
    }

    public function transition(int $complaintId, AppComplaintStatus $from, AppComplaintStatus $to, DateTimeImmutable $now, array $changes = []): bool
    {
        $sets = ['status = :to', 'updated_at = :now'];
        $params = ['to' => $to, 'now' => $now, 'id' => $complaintId, 'from' => $from];
        foreach ($changes as $key => $value) {
            $column = self::TRANSITION_COLUMNS[$key] ?? throw new LogicException('Неизвестное поле перехода жалобы: ' . $key);
            $sets[] = $column . ' = :change_' . $key;
            $params['change_' . $key] = $value;
        }

        return $this->db->execute(
            'UPDATE test_app_complaint SET ' . implode(', ', $sets)
            . ' WHERE id = :id AND status = :from AND deleted_at IS NULL',
            $params,
        ) === 1;
    }

    public function softDelete(int $complaintId, DateTimeImmutable $now): bool
    {
        $deleted = $this->db->execute(
            'UPDATE test_app_complaint SET deleted_at = :deleted, updated_at = :updated'
            . ' WHERE id = :id AND deleted_at IS NULL AND status IN (:draft, :awaiting, :signed)',
            [
                'deleted' => $now,
                'updated' => $now,
                'id' => $complaintId,
                'draft' => AppComplaintStatus::Draft,
                'awaiting' => AppComplaintStatus::AwaitingSignature,
                'signed' => AppComplaintStatus::Signed,
            ],
        ) === 1;
        if ($deleted) {
            $this->db->execute(
                'UPDATE test_app_complaint_lots SET deleted_at = :deleted, updated_at = :updated'
                . ' WHERE test_app_complaint_id = :id AND deleted_at IS NULL',
                ['deleted' => $now, 'updated' => $now, 'id' => $complaintId],
            );
        }

        return $deleted;
    }

    public function lockApplicationLot(int $appLotId): void
    {
        $this->db->value(
            "SELECT pg_advisory_xact_lock(hashtext('complaints.app_lot'), (CAST(:id AS bigint) % 2147483647)::int)",
            ['id' => $appLotId],
        );
    }

    public function hasActiveComplaint(int $appLotId, DateTimeImmutable $appealEndsAt, ?int $exceptComplaintId): bool
    {
        $params = ['app_lot' => $appLotId, 'appeal_ends' => $appealEndsAt];
        $except = '';
        if ($exceptComplaintId !== null) {
            $except = ' AND c.id <> :except';
            $params['except'] = $exceptComplaintId;
        }

        return $this->values->bool($this->db->value(
            'SELECT EXISTS (SELECT 1 FROM test_app_complaint_lots l'
            . ' JOIN test_app_complaint c ON c.id = l.test_app_complaint_id'
            . ' WHERE l.test_app_lots_id = :app_lot AND l.deleted_at IS NULL AND c.deleted_at IS NULL'
            . ' AND c.appeal_ends_at = :appeal_ends' . $except . ')',
            $params,
        ));
    }

    public function collectionBatches(DateTimeImmutable $appealEndedBy): array
    {
        $rows = $this->db->rows(
            'SELECT buy_id, appeal_starts_at, appeal_ends_at, MIN(protocol_published_at) AS protocol_published_at'
            . ' FROM test_app_complaint'
            . ' WHERE status = :status AND deleted_at IS NULL AND appeal_ends_at <= :ended_by'
            . ' GROUP BY buy_id, appeal_starts_at, appeal_ends_at'
            . ' ORDER BY appeal_ends_at, buy_id',
            ['status' => AppComplaintStatus::Submitted, 'ended_by' => $appealEndedBy],
        );

        return array_map(
            fn (array $row): CollectionBatch => new CollectionBatch(
                $this->values->int($row['buy_id']),
                $this->values->period($row['appeal_starts_at'], $row['appeal_ends_at']),
                $this->values->moment($row['protocol_published_at']),
            ),
            $rows,
        );
    }

    public function lockSubmittedOfBatch(int $buyId, DateTimeImmutable $appealEndsAt): array
    {
        return $this->many(
            'WHERE c.buy_id = :buy AND c.appeal_ends_at = :appeal_ends AND c.status = :status AND c.deleted_at IS NULL'
            . ' ORDER BY c.id FOR UPDATE',
            ['buy' => $buyId, 'appeal_ends' => $appealEndsAt, 'status' => AppComplaintStatus::Submitted],
        );
    }

    public function ofBuyComplaint(int $buyComplaintId): array
    {
        return $this->many(
            'WHERE c.test_buy_complaint_id = :decision AND c.deleted_at IS NULL ORDER BY c.id',
            ['decision' => $buyComplaintId],
        );
    }

    public function lotOf(int $complaintId): AppComplaintLot
    {
        return $this->oneLot('WHERE l.test_app_complaint_id = :complaint AND l.deleted_at IS NULL', ['complaint' => $complaintId]);
    }

    public function lot(int $lotId): AppComplaintLot
    {
        return $this->oneLot('WHERE l.id = :id', ['id' => $lotId]);
    }

    public function addLot(int $complaintId, ApplicationLotCard $appLot, DateTimeImmutable $now): int
    {
        return $this->values->int($this->db->value(
            'INSERT INTO test_app_complaint_lots (test_app_complaint_id, test_app_id, test_app_lots_id, test_buy_lots_id, created_at, updated_at)'
            . ' VALUES (:complaint, :app, :app_lot, :buy_lot, :created, :updated) RETURNING id',
            [
                'complaint' => $complaintId,
                'app' => $appLot->appId,
                'app_lot' => $appLot->id,
                'buy_lot' => $appLot->buyLotId,
                'created' => $now,
                'updated' => $now,
            ],
        ));
    }

    public function removeLot(int $lotId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_app_complaint_lots SET deleted_at = :deleted, updated_at = :updated WHERE id = :id AND deleted_at IS NULL',
            ['deleted' => $now, 'updated' => $now, 'id' => $lotId],
        );
    }

    public function lotsOfBuyComplaintLot(int $buyComplaintLotId): array
    {
        return $this->manyLots(
            'WHERE l.test_buy_complaint_lots_id = :decision_lot AND l.deleted_at IS NULL ORDER BY l.id',
            ['decision_lot' => $buyComplaintLotId],
        );
    }

    public function lotsOfBuyComplaint(int $buyComplaintId): array
    {
        return $this->manyLots(
            'JOIN test_buy_complaint_lots bl ON bl.id = l.test_buy_complaint_lots_id'
            . ' WHERE bl.test_buy_complaint_id = :decision AND l.deleted_at IS NULL ORDER BY l.id',
            ['decision' => $buyComplaintId],
        );
    }

    public function linkToBuyComplaintLot(int $lotId, int $buyComplaintLotId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_app_complaint_lots SET test_buy_complaint_lots_id = :decision_lot, updated_at = :now WHERE id = :id',
            ['decision_lot' => $buyComplaintLotId, 'now' => $now, 'id' => $lotId],
        );
    }

    public function setVerdict(int $lotId, ?Verdict $verdict, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_app_complaint_lots SET verdict = :verdict, updated_at = :now WHERE id = :id',
            ['verdict' => $verdict, 'now' => $now, 'id' => $lotId],
        );
    }

    public function setAnswer(int $lotId, ?int $answerId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_app_complaint_lots SET test_complaint_answers_id = :answer, updated_at = :now WHERE id = :id',
            ['answer' => $answerId, 'now' => $now, 'id' => $lotId],
        );
    }

    public function clearAnswers(int $buyComplaintId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_app_complaint_lots l SET test_complaint_answers_id = NULL, updated_at = :now'
            . ' FROM test_buy_complaint_lots bl'
            . ' WHERE bl.id = l.test_buy_complaint_lots_id AND bl.test_buy_complaint_id = :decision'
            . ' AND l.test_complaint_answers_id IS NOT NULL',
            ['now' => $now, 'decision' => $buyComplaintId],
        );
    }

    public function markReviewed(int $buyComplaintId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_app_complaint_lots l SET reviewed_at = :reviewed, updated_at = :updated'
            . ' FROM test_buy_complaint_lots bl'
            . ' WHERE bl.id = l.test_buy_complaint_lots_id AND bl.test_buy_complaint_id = :decision'
            . ' AND l.deleted_at IS NULL',
            ['reviewed' => $now, 'updated' => $now, 'decision' => $buyComplaintId],
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws NotFound
     */
    private function one(string $condition, array $params): AppComplaint
    {
        $row = $this->db->row('SELECT ' . AppComplaintMapper::COLUMNS . ' FROM test_app_complaint c ' . $condition, $params);

        return $row === null ? throw new NotFound() : $this->mapper->complaint($row);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<AppComplaint>
     */
    private function many(string $condition, array $params): array
    {
        return array_map(
            fn (array $row): AppComplaint => $this->mapper->complaint($row),
            $this->db->rows('SELECT ' . AppComplaintMapper::COLUMNS . ' FROM test_app_complaint c ' . $condition, $params),
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws NotFound
     */
    private function oneLot(string $condition, array $params): AppComplaintLot
    {
        $row = $this->db->row('SELECT ' . AppComplaintMapper::LOT_COLUMNS . ' FROM test_app_complaint_lots l ' . $condition, $params);

        return $row === null ? throw new NotFound() : $this->mapper->lot($row);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<AppComplaintLot>
     */
    private function manyLots(string $condition, array $params): array
    {
        return array_map(
            fn (array $row): AppComplaintLot => $this->mapper->lot($row),
            $this->db->rows('SELECT ' . AppComplaintMapper::LOT_COLUMNS . ' FROM test_app_complaint_lots l ' . $condition, $params),
        );
    }
}
