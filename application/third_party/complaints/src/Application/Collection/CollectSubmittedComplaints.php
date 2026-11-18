<?php

declare(strict_types=1);

namespace Complaints\Application\Collection;

use Complaints\Application\Access\ModuleRole;
use Complaints\Application\Port\AuthorityResolver;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\CollectionBatch;
use Complaints\Application\Repository\NewBuyComplaint;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Repository\StatusHistoryRepository;
use Complaints\Application\Repository\TransactionManager;
use Complaints\Application\Service\DeadlineCalculator;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Application\Shared\ComplaintSettings;
use Complaints\Application\Shared\DisplayDate;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\History\HistoryEntityType;
use DateInterval;
use DateTimeImmutable;

/**
 * Сбор жалоб (cron): по каждой паре (закупка, окончание срока обжалования)
 * с поданными жалобами в одной транзакции создаёт решение и лоты решения,
 * связывает с ними жалобы, переводит жалобы в in_review, пишет историю
 * и уведомляет рассматривающих органа.
 *
 * Идемпотентна: решение на пару одно (уникальный ключ buy_id, appeal_ends_at),
 * лот решения на лот закупки один, жалобы берутся только в статусе submitted.
 */
final class CollectSubmittedComplaints
{
    public function __construct(
        private readonly AppComplaintRepository $complaints,
        private readonly BuyComplaintRepository $decisions,
        private readonly ProcurementRepository $procurement,
        private readonly AuthorityResolver $authorities,
        private readonly DeadlineCalculator $deadlines,
        private readonly StatusTransitioner $transitions,
        private readonly StatusHistoryRepository $history,
        private readonly DocumentIssuer $documents,
        private readonly Notifier $notifier,
        private readonly TransactionManager $transactions,
        private readonly ComplaintSettings $settings,
        private readonly DisplayDate $dates,
        private readonly Clock $clock,
    ) {
    }

    public function execute(): CollectionReport
    {
        $now = $this->clock->now();
        $endedBy = $now->sub(new DateInterval('PT' . $this->settings->collectionGraceMinutes . 'M'));
        $report = new CollectionReport();

        foreach ($this->complaints->collectionBatches($endedBy) as $batch) {
            $this->transactions->transactional(fn () => $this->collect($batch, $now, $report));
        }

        return $report;
    }

    private function collect(CollectionBatch $batch, DateTimeImmutable $now, CollectionReport $report): void
    {
        $decision = $this->decisionFor($batch, $now, $report);
        if (!in_array($decision->status, [BuyComplaintStatus::InReview, BuyComplaintStatus::Recalled], true)) {
            $report->skipped[] = sprintf(
                'Решение %d в статусе %s: поданные жалобы закупки %d не добавлены.',
                $decision->id,
                $decision->status->value,
                $batch->buyId,
            );

            return;
        }

        $complaints = $this->complaints->lockSubmittedOfBatch($batch->buyId, $batch->appealPeriod->endsAt);
        foreach ($complaints as $complaint) {
            $lot = $this->complaints->lotOf($complaint->id);
            $decisionLotId = $this->decisions->ensureLot($decision->id, $lot->buyLotId, $now);
            $this->complaints->linkToBuyComplaintLot($lot->id, $decisionLotId, $now);
            $this->transitions->moveComplaint($complaint, AppComplaintStatus::InReview, null, $now, [
                'buy_complaint_id' => $decision->id,
            ]);
        }
        if ($complaints === []) {
            return;
        }

        // Сводный документ больше не охватывает все жалобы.
        $this->documents->retire(DocumentType::ConsolidatedAnswer, $decision->id, null, $now);
        $report->complaintsCollected += count($complaints);
        $this->notifyReviewers($decision, count($complaints));
    }

    private function decisionFor(CollectionBatch $batch, DateTimeImmutable $now, CollectionReport $report): BuyComplaint
    {
        $createdId = $this->decisions->create(new NewBuyComplaint(
            $batch->buyId,
            $this->authorities->authorityFor($batch->buyId),
            $batch->protocolPublishedAt,
            $batch->appealPeriod,
            $this->deadlines->reviewPeriod($batch->appealPeriod),
        ), $now);

        if ($createdId !== null) {
            $report->decisionsCreated++;
            $this->history->append(
                HistoryEntityType::BuyComplaint,
                $createdId,
                null,
                BuyComplaintStatus::InReview->value,
                null,
                $now,
            );

            return $this->decisions->lock($createdId);
        }

        $existing = $this->decisions->findByBuyAndAppealEnd($batch->buyId, $batch->appealPeriod->endsAt);

        return $this->decisions->lock($existing?->id ?? 0);
    }

    private function notifyReviewers(BuyComplaint $decision, int $count): void
    {
        $notification = new Notification(
            NotificationType::ComplaintsReceived,
            [
                'buy' => $this->procurement->buy($decision->buyId)->number,
                'count' => (string) $count,
                'date' => $this->dates->moment($decision->reviewPeriod->displayEndsAt()),
            ],
            NotificationTarget::Decision,
            $decision->id,
        );

        $recipients = $decision->reviewerUserId === null
            ? array_map(static fn ($user): int => $user->id, $this->authorities->employees($decision->authorityOrgId, ModuleRole::Reviewer))
            : [$decision->reviewerUserId];
        foreach ($recipients as $userId) {
            $this->notifier->notifyUser($userId, $notification);
        }
    }
}
