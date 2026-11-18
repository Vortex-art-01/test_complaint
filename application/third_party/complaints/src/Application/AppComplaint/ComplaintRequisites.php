<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintLot;

/**
 * Реквизиты жалобы: для черновика — текущие из справочников,
 * после формирования PDF — зафиксированные в requisites_snapshot.
 */
final class ComplaintRequisites
{
    public function __construct(
        private readonly ProcurementRepository $procurement,
        private readonly OrganizationDirectory $directory,
    ) {
    }

    public function of(AppComplaint $complaint, AppComplaintLot $lot): Requisites
    {
        return $complaint->requisitesSnapshot !== null
            ? Requisites::fromArray($complaint->requisitesSnapshot)
            : $this->current($complaint, $lot);
    }

    /**
     * Текущие реквизиты из справочников проекта.
     */
    public function current(AppComplaint $complaint, AppComplaintLot $lot): Requisites
    {
        $buy = $this->procurement->buy($complaint->buyId);
        $buyLot = $this->procurement->buyLot($lot->buyLotId);
        $organizer = $this->directory->organization($buy->organizerOrgId);
        $supplier = $this->directory->organization($complaint->supplierOrgId);
        $author = $this->directory->user($complaint->authorUserId);
        $head = $this->directory->user($complaint->headUserId);

        return new Requisites(
            $buy->number,
            $buy->name,
            $buy->publishedAt,
            $buyLot->number,
            $buyLot->name,
            $organizer->bin,
            $organizer->name,
            $organizer->legalAddress,
            $supplier->bin,
            $supplier->name,
            $supplier->legalAddress,
            $author->fullName,
            $author->iin,
            $author->email,
            $head->fullName,
            $head->position,
        );
    }
}
