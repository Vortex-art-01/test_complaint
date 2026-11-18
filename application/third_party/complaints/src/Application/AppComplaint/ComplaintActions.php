<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\CurrentUser;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintStatus as Status;
use DateTimeImmutable;

/**
 * Какие действия над жалобой доступны пользователю сейчас.
 * Сценарии проверяют то же самое на сервере перед изменением.
 */
final class ComplaintActions
{
    public function __construct(
        private readonly AccessGuard $guard,
    ) {
    }

    /**
     * @return list<ComplaintAction>
     */
    public function available(CurrentUser $user, AppComplaint $complaint, DateTimeImmutable $now): array
    {
        $isAuthor = $this->guard->isComplaintAuthor($user, $complaint);
        $isHead = $this->guard->isComplaintHead($user, $complaint);
        $appealOpen = $complaint->isAppealOpenAt($now);
        $status = $complaint->status;

        $rules = [
            ComplaintAction::Edit->value => $isAuthor && $appealOpen && $status === Status::Draft,
            ComplaintAction::Sign->value => $isHead && $appealOpen && $status === Status::AwaitingSignature,
            ComplaintAction::ReturnToDraft->value => $isAuthor && $appealOpen
                && in_array($status, [Status::AwaitingSignature, Status::Signed, Status::Withdrawn], true),
            ComplaintAction::Submit->value => ($isAuthor || $isHead) && $appealOpen
                && in_array($status, [Status::Signed, Status::Withdrawn], true),
            ComplaintAction::Withdraw->value => $isAuthor && $appealOpen && $status === Status::Submitted,
            ComplaintAction::Delete->value => $isAuthor && $status->isDeletable(),
        ];

        return array_values(array_map(
            static fn (string $action): ComplaintAction => ComplaintAction::from($action),
            array_keys(array_filter($rules)),
        ));
    }

    public function allows(CurrentUser $user, AppComplaint $complaint, ComplaintAction $action, DateTimeImmutable $now): bool
    {
        return in_array($action, $this->available($user, $complaint, $now), true);
    }
}
