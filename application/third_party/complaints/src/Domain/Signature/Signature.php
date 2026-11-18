<?php

declare(strict_types=1);

namespace Complaints\Domain\Signature;

use DateTimeImmutable;

/**
 * Подпись версии документа (снимок строки test_complaint_signatures).
 */
final readonly class Signature
{
    public function __construct(
        public int $id,
        public int $documentId,
        public SignerRole $role,
        public int $signerUserId,
        public int $signOrder,
        public SignatureStatus $status,
        public ?int $signatureFileId,
        public ?string $certificateSubject,
        public ?DateTimeImmutable $signedAt,
        public ?DateTimeImmutable $annulledAt,
    ) {
    }

    public function isSigned(): bool
    {
        return $this->status === SignatureStatus::Signed;
    }

    public function isPending(): bool
    {
        return $this->status === SignatureStatus::Pending;
    }
}
