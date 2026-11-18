<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

use InvalidArgumentException;

/**
 * Номер решения по жалобам: «Р-<номер закупки>» для первого цикла
 * обжалования закупки, «Р-<номер закупки>/<N>» для следующих.
 */
final readonly class DecisionNumber
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function forCycle(string $buyNumber, int $cycle): self
    {
        $buyNumber = trim($buyNumber);
        if ($buyNumber === '') {
            throw new InvalidArgumentException('Номер закупки не задан.');
        }
        if ($cycle < 1) {
            throw new InvalidArgumentException('Номер цикла обжалования начинается с 1.');
        }

        $value = 'Р-' . $buyNumber;

        return new self($cycle === 1 ? $value : $value . '/' . $cycle);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
