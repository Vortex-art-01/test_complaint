<?php

declare(strict_types=1);

namespace Complaints\Application\Execution\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\ModuleRole;
use Complaints\Application\BuyComplaint\DecisionRegistryItem;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\ProcurementLotGateway;
use Complaints\Application\Repository\ComplaintQueries;
use Complaints\Application\Repository\DecisionListRow;
use Complaints\Application\Shared\ComplaintSettings;
use Complaints\Application\Shared\Page;
use Complaints\Domain\BuyComplaint\ExecutionStatus;

/**
 * Реестр «Для организатора»: утверждённые решения по закупкам организации.
 */
final class ListOrganizerDecisions
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly ComplaintQueries $queries,
        private readonly OrganizationDirectory $directory,
        private readonly ProcurementLotGateway $lotGateway,
        private readonly ComplaintSettings $settings,
    ) {
    }

    /**
     * @return Page<DecisionRegistryItem>
     *
     * @throws AccessDenied
     */
    public function execute(?ExecutionStatus $status, ?string $buyNumber, int $page): Page
    {
        $user = $this->users->currentUser();
        $this->guard->assertAllowed($user->hasRole(ModuleRole::Organizer));

        $rows = $this->queries->organizerDecisions(
            $user->organizationId,
            $status,
            trim((string) $buyNumber) === '' ? null : trim((string) $buyNumber),
            $page,
            $this->settings->pageSize,
        );
        $organizer = $this->directory->organization($user->organizationId);

        return new Page(
            array_map(
                fn (DecisionListRow $row): DecisionRegistryItem => new DecisionRegistryItem(
                    $row,
                    $organizer,
                    $this->directory->organization($row->decision->authorityOrgId),
                    '',
                    $this->lotGateway->procurementStatusName($row->decision->buyId),
                ),
                $rows->items,
            ),
            $rows->total,
            $rows->page,
            $rows->pageSize,
        );
    }
}
