<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Shared\Page;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\ExecutionStatus;

/**
 * Списки для реестров: соединяют таблицы модуля с номерами и названиями закупок.
 */
interface ComplaintQueries
{
    /**
     * Неудалённые жалобы организации, новые сверху.
     *
     * @return Page<ComplaintListRow>
     */
    public function organizationComplaints(int $organizationId, int $page, int $pageSize): Page;

    /**
     * Решения уполномоченного органа.
     *
     * @return Page<DecisionListRow>
     */
    public function authorityDecisions(int $authorityOrgId, ?BuyComplaintStatus $status, ?string $buyNumber, int $page, int $pageSize): Page;

    /**
     * Утверждённые решения по закупкам организатора.
     *
     * @return Page<DecisionListRow>
     */
    public function organizerDecisions(int $organizerOrgId, ?ExecutionStatus $status, ?string $buyNumber, int $page, int $pageSize): Page;

    /**
     * Лоты решения со сводкой, по номеру лота.
     *
     * @return list<DecisionLotRow>
     */
    public function decisionLots(int $buyComplaintId): array;
}
