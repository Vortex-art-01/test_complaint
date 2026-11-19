<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Shared\LocalizedText;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\BuyComplaint\Verdict;

/**
 * Строка реестра жалоб организации.
 */
final readonly class ComplaintListRow
{
    public function __construct(
        public AppComplaint $complaint,
        public string $buyNumber,
        public LocalizedText $buyName,
        public string $lotNumber,
        public ?Verdict $verdict,
    ) {
    }
}
