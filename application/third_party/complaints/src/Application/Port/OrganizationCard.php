<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Shared\LocalizedText;

/**
 * Реквизиты организации для экранов и PDF.
 */
final readonly class OrganizationCard
{
    /**
     * @param string $bin БИН, у индивидуального предпринимателя — ИИН
     */
    public function __construct(
        public int $id,
        public string $bin,
        public LocalizedText $name,
        public LocalizedText $legalAddress,
    ) {
    }
}
