<?php

declare(strict_types=1);

namespace Complaints\Domain\Shared;

use BackedEnum;

/**
 * Переход статуса, не разрешённый правилами.
 */
final class InvalidStatusTransition extends DomainRuleViolation
{
    public static function between(BackedEnum $from, BackedEnum $to): self
    {
        return new self(sprintf('Переход статуса %s -> %s запрещён.', $from->value, $to->value));
    }
}
