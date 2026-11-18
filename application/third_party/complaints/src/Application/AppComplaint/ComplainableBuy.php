<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Repository\ApplicationCard;
use Complaints\Application\Repository\BuyCard;

/**
 * Закупка, которую организация может обжаловать, её заявка и открытые лоты.
 */
final readonly class ComplainableBuy
{
    /**
     * @param list<ComplainableLot> $lots
     */
    public function __construct(
        public BuyCard $buy,
        public ApplicationCard $application,
        public array $lots,
    ) {
    }
}
