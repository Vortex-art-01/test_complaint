<?php

declare(strict_types=1);

namespace Complaints\Domain\Shared;

use DomainException;

/**
 * Нарушение правила предметной области.
 */
class DomainRuleViolation extends DomainException
{
}
