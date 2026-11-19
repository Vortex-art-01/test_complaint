<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Решение по жалобам на закупку: один цикл обжалования (снимок строки trd_complaint).
 * Номер решения — его id. Участники маршрута хранятся в trd_complaint_member.
 *
 * Даты маршрута: sentForApprovalAt — рассматривающий отправил решение (с согласующим
 * и без), coordinatedAt — согласующий подписал и передал утверждающему.
 */
final readonly class BuyComplaint
{
    public function __construct(
        public int $id,
        public int $buyId,
        public int $authorityOrgId,
        public Period $appealPeriod,
        public Period $reviewPeriod,
        public BuyComplaintStatus $status,
        public ?ReviewMethod $reviewMethod,
        public ?int $reviewerUserId,
        public ?int $coordinatorUserId,
        public ?int $approverUserId,
        public ?DateTimeImmutable $sentForApprovalAt,
        public ?int $sentByUserId,
        public ?DateTimeImmutable $coordinatedAt,
        public ?DateTimeImmutable $approvedAt,
        public ?DateTimeImmutable $recalledAt,
        public ?int $recalledByUserId,
        public ?ExecutionStatus $executionStatus,
        public ?Period $executionPeriod,
        public ?DateTimeImmutable $executedAt,
    ) {
    }

    public function isReviewedBy(int $userId): bool
    {
        return $this->reviewerUserId === $userId;
    }

    public function hasReviewer(): bool
    {
        return $this->reviewerUserId !== null;
    }

    public function hasCoordinator(): bool
    {
        return $this->coordinatorUserId !== null;
    }

    /**
     * С какого момента решение у утверждающего: после согласования или сразу после отправки.
     */
    public function onApprovalSince(): ?DateTimeImmutable
    {
        return $this->coordinatedAt ?? ($this->hasCoordinator() ? null : $this->sentForApprovalAt);
    }

    public function isReviewOverdueAt(DateTimeImmutable $moment): bool
    {
        return $this->status !== BuyComplaintStatus::Approved && $this->reviewPeriod->hasEndedAt($moment);
    }

    public function isExecutionOverdueAt(DateTimeImmutable $moment): bool
    {
        return $this->executionStatus === ExecutionStatus::InProgress
            && $this->executionPeriod !== null
            && $this->executionPeriod->hasEndedAt($moment);
    }
}
