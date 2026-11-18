<?php

declare(strict_types=1);

namespace Complaints\Domain\Shared;

use InvalidArgumentException;

/**
 * Хеш SHA-256 байтов документа: 64 строчные шестнадцатеричные цифры.
 */
final readonly class Sha256
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function ofBytes(string $bytes): self
    {
        return new self(hash('sha256', $bytes));
    }

    public static function fromHex(string $hex): self
    {
        if (preg_match('/^[0-9a-f]{64}$/', $hex) !== 1) {
            throw new InvalidArgumentException('Ожидается SHA-256 в виде 64 строчных шестнадцатеричных цифр.');
        }

        return new self($hex);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
