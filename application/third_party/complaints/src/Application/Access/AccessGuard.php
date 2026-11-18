<?php

declare(strict_types=1);

namespace Complaints\Application\Access;

use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Port\AccessPolicy;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;

/**
 * Правила доступа модуля: роль модуля + разрешение проекта (AccessPolicy)
 * + связь пользователя с записью (автор, руководитель, маршрут, орган).
 */
final class AccessGuard
{
    public function __construct(
        private readonly AccessPolicy $policy,
    ) {
    }

    /**
     * @throws AccessDenied
     */
    public function assertAllowed(bool $allowed): void
    {
        if (!$allowed) {
            throw new AccessDenied();
        }
    }

    public function can(CurrentUser $user, Permission $permission, int $buyId): bool
    {
        return $user->hasRole($permission->requiredRole())
            && $this->policy->isAllowed($user, $permission, $buyId);
    }

    public function isComplaintAuthor(CurrentUser $user, AppComplaint $complaint): bool
    {
        return $complaint->isAuthoredBy($user->userId) && $user->belongsTo($complaint->supplierOrgId);
    }

    public function isComplaintHead(CurrentUser $user, AppComplaint $complaint): bool
    {
        return $complaint->isHeadedBy($user->userId)
            && $user->belongsTo($complaint->supplierOrgId)
            && $user->hasRole(ModuleRole::SupplierHead);
    }

    public function canViewComplaint(CurrentUser $user, AppComplaint $complaint, ?BuyComplaint $decision): bool
    {
        if ($user->belongsTo($complaint->supplierOrgId)) {
            return true;
        }

        return $decision !== null && $this->canViewDecision($user, $decision);
    }

    public function isAuthorityMember(CurrentUser $user, BuyComplaint $decision): bool
    {
        return $user->belongsTo($decision->authorityOrgId)
            && $user->hasAnyRole(ModuleRole::Reviewer, ModuleRole::Coordinator, ModuleRole::Approver);
    }

    public function canViewDecision(CurrentUser $user, BuyComplaint $decision): bool
    {
        return $this->isAuthorityMember($user, $decision) || $this->isOrganizerOf($user, $decision);
    }

    /**
     * Рассматривающий органа; если решение закреплено — только закреплённый.
     */
    public function canReview(CurrentUser $user, BuyComplaint $decision): bool
    {
        return $user->belongsTo($decision->authorityOrgId)
            && $this->can($user, Permission::ReviewComplaints, $decision->buyId)
            && (!$decision->hasReviewer() || $decision->isReviewedBy($user->userId));
    }

    public function canCoordinate(CurrentUser $user, BuyComplaint $decision): bool
    {
        return $decision->coordinatorUserId === $user->userId
            && $user->belongsTo($decision->authorityOrgId)
            && $this->can($user, Permission::CoordinateDecision, $decision->buyId);
    }

    public function canApprove(CurrentUser $user, BuyComplaint $decision): bool
    {
        return $decision->approverUserId === $user->userId
            && $user->belongsTo($decision->authorityOrgId)
            && $this->can($user, Permission::ApproveDecision, $decision->buyId);
    }

    /**
     * Снять закрепление может утверждающий того же органа.
     */
    public function canRelease(CurrentUser $user, BuyComplaint $decision): bool
    {
        return $decision->hasReviewer()
            && $user->belongsTo($decision->authorityOrgId)
            && $this->can($user, Permission::ApproveDecision, $decision->buyId);
    }

    /**
     * Организатор закупки видит и исполняет только утверждённое решение.
     */
    public function isOrganizerOf(CurrentUser $user, BuyComplaint $decision): bool
    {
        return $decision->status === BuyComplaintStatus::Approved
            && $this->can($user, Permission::ExecuteDecision, $decision->buyId);
    }
}
