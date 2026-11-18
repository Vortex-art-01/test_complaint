<?php

declare(strict_types=1);

namespace Complaints\Domain\AppComplaint;

use InvalidArgumentException;

/**
 * Номер жалобы «Ж-000001». Присваивает БД (последовательность).
 */
final readonly class ComplaintNumber
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/^Ж-\d{6,}$/u', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Неверный номер жалобы: %s.', $value));
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
