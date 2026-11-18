<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\ModuleRole;
use Complaints\Application\BuyComplaint\DecisionRegistryItem;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Repository\ComplaintQueries;
use Complaints\Application\Repository\DecisionListRow;
use Complaints\Application\Shared\ComplaintSettings;
use Complaints\Application\Shared\Page;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;

/**
 * Реестр «Для проверяющих органов»: решения органа пользователя.
 */
final class ListAuthorityDecisions
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly ComplaintQueries $queries,
        private readonly OrganizationDirectory $directory,
        private readonly ComplaintSettings $settings,
    ) {
    }

    /**
     * @return Page<DecisionRegistryItem>
     *
     * @throws AccessDenied
     */
    public function execute(?BuyComplaintStatus $status, ?string $buyNumber, int $page): Page
    {
        $user = $this->users->currentUser();
        $this->guard->assertAllowed($user->hasAnyRole(ModuleRole::Reviewer, ModuleRole::Coordinator, ModuleRole::Approver));

        $rows = $this->queries->authorityDecisions(
            $user->organizationId,
            $status,
            trim((string) $buyNumber) === '' ? null : trim((string) $buyNumber),
            $page,
            $this->settings->pageSize,
        );
        $authority = $this->directory->organization($user->organizationId);

        return new Page(
            array_map(
                fn (DecisionListRow $row): DecisionRegistryItem => new DecisionRegistryItem(
                    $row,
                    $this->directory->organization($row->organizerOrgId),
                    $authority,
                    $row->decision->reviewerUserId === null ? '' : $this->directory->user($row->decision->reviewerUserId)->fullName,
                    '',
                ),
                $rows->items,
            ),
            $rows->total,
            $rows->page,
            $rows->pageSize,
        );
    }
}
