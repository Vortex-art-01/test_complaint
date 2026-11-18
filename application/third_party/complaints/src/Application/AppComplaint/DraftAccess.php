<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Shared\DisplayDate;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use DateTimeImmutable;

/**
 * Общие проверки сценариев автора над жалобой: блокировка строки,
 * авторство, допустимый статус, открытый срок обжалования.
 */
final class DraftAccess
{
    public function __construct(
        private readonly AppComplaintRepository $complaints,
        private readonly AccessGuard $guard,
        private readonly DisplayDate $dates,
    ) {
    }

    /**
     * Блокирует жалобу автора в одном из статусов при открытом сроке.
     *
     * @param list<AppComplaintStatus> $statuses
     *
     * @throws AccessDenied
     * @throws StatusConflict
     * @throws ValidationFailed срок обжалования закрыт
     */
    public function lockForAuthor(CurrentUser $user, int $complaintId, array $statuses, DateTimeImmutable $now): AppComplaint
    {
        $complaint = $this->complaints->lock($complaintId);
        $this->guard->assertAllowed($this->guard->isComplaintAuthor($user, $complaint));
        $this->assertStatus($complaint, $statuses);
        $this->assertAppealOpen($complaint, $now);

        return $complaint;
    }

    /**
     * @param list<AppComplaintStatus> $statuses
     *
     * @throws StatusConflict
     */
    public function assertStatus(AppComplaint $complaint, array $statuses): void
    {
        if (!in_array($complaint->status, $statuses, true)) {
            throw new StatusConflict();
        }
    }

    /**
     * @throws ValidationFailed
     */
    public function assertAppealOpen(AppComplaint $complaint, DateTimeImmutable $now): void
    {
        if (!$complaint->isAppealOpenAt($now)) {
            throw ValidationFailed::field('form', 'complaints_error_appeal_closed', [
                'date' => $this->dates->moment($complaint->appealPeriod->displayEndsAt()),
            ]);
        }
    }
}
