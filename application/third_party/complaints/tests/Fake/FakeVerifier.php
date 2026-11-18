<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Exception\SignatureRejected;
use Complaints\Application\Port\SignatureVerifier;
use Complaints\Application\Port\VerifiedSignature;

final class FakeVerifier implements SignatureVerifier
{
    /** @var array<int, VerifiedSignature> пользователь => что вернёт проверка */
    public array $certificates = [];

    public ?int $signingUserId = null;
    public bool $reject = false;

    public function verify(string $documentBytes, string $cmsBase64): VerifiedSignature
    {
        if ($this->reject || $this->signingUserId === null) {
            throw new SignatureRejected();
        }

        return $this->certificates[$this->signingUserId];
    }
}
