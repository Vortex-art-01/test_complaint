<?php

declare(strict_types=1);

namespace Complaints\Domain\AppComplaint;

use InvalidArgumentException;

/**
 * Номер жалобы для людей: «Ж-000015». Отдельно не хранится — строится из id.
 */
final readonly class ComplaintNumber
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function of(int $complaintId): self
    {
        if ($complaintId < 1) {
            throw new InvalidArgumentException(sprintf('Неверный id жалобы: %d.', $complaintId));
        }

        return new self('Ж-' . str_pad((string) $complaintId, 6, '0', STR_PAD_LEFT));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
