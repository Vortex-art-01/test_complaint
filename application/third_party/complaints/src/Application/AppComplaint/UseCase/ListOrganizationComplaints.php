<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\ModuleRole;
use Complaints\Application\AppComplaint\ComplaintActions;
use Complaints\Application\AppComplaint\ComplaintRegistryItem;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\ComplaintListRow;
use Complaints\Application\Repository\ComplaintQueries;
use Complaints\Application\Shared\ComplaintSettings;
use Complaints\Application\Shared\Page;

/**
 * Реестр «Жалобы организации».
 */
final class ListOrganizationComplaints
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly ComplaintQueries $queries,
        private readonly ComplaintActions $actions,
        private readonly ComplaintSettings $settings,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return Page<ComplaintRegistryItem>
     *
     * @throws AccessDenied
     */
    public function execute(int $page): Page
    {
        $user = $this->users->currentUser();
        $this->guard->assertAllowed($user->hasAnyRole(ModuleRole::Supplier, ModuleRole::SupplierHead));
        $now = $this->clock->now();

        $rows = $this->queries->organizationComplaints($user->organizationId, $page, $this->settings->pageSize);

        return new Page(
            array_map(
                fn (ComplaintListRow $row): ComplaintRegistryItem => new ComplaintRegistryItem(
                    $row,
                    $this->actions->available($user, $row->complaint, $now),
                    $row->complaint->isAppealOpenAt($now),
                ),
                $rows->items,
            ),
            $rows->total,
            $rows->page,
            $rows->pageSize,
        );
    }
}
