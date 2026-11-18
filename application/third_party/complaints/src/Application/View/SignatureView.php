<?php

declare(strict_types=1);

namespace Complaints\Application\View;

use Complaints\Domain\Signature\Signature;

/**
 * Подпись и ФИО подписанта.
 */
final readonly class SignatureView
{
    public function __construct(
        public Signature $signature,
        public string $signerName,
    ) {
    }
}
