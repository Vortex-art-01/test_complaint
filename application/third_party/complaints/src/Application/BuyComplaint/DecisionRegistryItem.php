<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

use Complaints\Application\Port\OrganizationCard;
use Complaints\Application\Repository\DecisionListRow;

/**
 * Строка реестра решений с названиями организаций.
 */
final readonly class DecisionRegistryItem
{
    public function __construct(
        public DecisionListRow $row,
        public OrganizationCard $organizer,
        public OrganizationCard $authority,
        public string $reviewerName,
        public string $procurementStatusName,
    ) {
    }
}
