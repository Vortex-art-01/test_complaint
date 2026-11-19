<?php

declare(strict_types=1);

namespace Complaints\Domain\Signature;

use DateTimeImmutable;

/**
 * Подпись документа участником маршрута: ожидается или поставлена.
 * Сами подписи хранятся у файла в хранилище проекта.
 */
final readonly class Signature
{
    public function __construct(
        public SignerRole $role,
        public int $signerUserId,
        public SignatureStatus $status,
        public ?string $certificateSubject,
        public ?DateTimeImmutable $signedAt,
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
