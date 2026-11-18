<?php

declare(strict_types=1);

namespace Complaints\Application\Notification;

use Complaints\Application\Access\ModuleRole;
use Complaints\Application\Port\AuthorityResolver;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Shared\DisplayDate;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use DateInterval;
use DateTimeImmutable;

/**
 * Напоминания о сроках (cron раз в сутки): срок рассмотрения или исполнения
 * заканчивается в ближайшие 24 часа. Сроки информативные: действия
 * после них не блокируются.
 */
final class SendDeadlineReminders
{
    public function __construct(
        private readonly BuyComplaintRepository $decisions,
        private readonly ProcurementRepository $procurement,
        private readonly AuthorityResolver $authorities,
        private readonly Notifier $notifier,
        private readonly DisplayDate $dates,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return int сколько уведомлений отправлено
     */
    public function execute(): int
    {
        $now = $this->clock->now();
        $until = $now->add(new DateInterval('P1D'));
        $sent = 0;

        foreach ($this->decisions->reviewEndingBetween($now, $until) as $decision) {
            $notification = $this->notification($decision, NotificationType::ReviewDeadline, NotificationTarget::Decision, $decision->reviewPeriod->displayEndsAt());
            foreach ($this->reviewRecipients($decision) as $userId) {
                $this->notifier->notifyUser($userId, $notification);
                $sent++;
            }
        }

        foreach ($this->decisions->executionEndingBetween($now, $until) as $decision) {
            if ($decision->executionPeriod === null) {
                continue;
            }
            $this->notifier->notifyOrganization(
                $this->procurement->buy($decision->buyId)->organizerOrgId,
                $this->notification($decision, NotificationType::ExecutionDeadline, NotificationTarget::Execution, $decision->executionPeriod->displayEndsAt()),
            );
            $sent++;
        }

        return $sent;
    }

    private function notification(BuyComplaint $decision, NotificationType $type, NotificationTarget $target, DateTimeImmutable $deadline): Notification
    {
        return new Notification(
            $type,
            ['buy' => $this->procurement->buy($decision->buyId)->number, 'date' => $this->dates->moment($deadline)],
            $target,
            $decision->id,
        );
    }

    /**
     * Кто сейчас держит решение: согласующий, утверждающий или рассматривающий.
     *
     * @return list<int>
     */
    private function reviewRecipients(BuyComplaint $decision): array
    {
        return match (true) {
            $decision->status === BuyComplaintStatus::OnCoordination && $decision->coordinatorUserId !== null => [$decision->coordinatorUserId],
            $decision->status === BuyComplaintStatus::OnApproval && $decision->approverUserId !== null => [$decision->approverUserId],
            $decision->reviewerUserId !== null => [$decision->reviewerUserId],
            default => array_map(
                static fn ($card): int => $card->id,
                $this->authorities->employees($decision->authorityOrgId, ModuleRole::Reviewer),
            ),
        };
    }
}
