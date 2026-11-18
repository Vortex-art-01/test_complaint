<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Repository\ApplicationLotCard;
use Complaints\Application\Repository\BuyLotCard;
use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Лот заявки, по которому сейчас можно подать жалобу.
 */
final readonly class ComplainableLot
{
    public function __construct(
        public ApplicationLotCard $appLot,
        public BuyLotCard $buyLot,
        public DateTimeImmutable $protocolPublishedAt,
        public Period $appealPeriod,
    ) {
    }
}
