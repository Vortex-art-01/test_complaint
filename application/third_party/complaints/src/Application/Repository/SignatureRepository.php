<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Exception\NotFound;
use Complaints\Domain\Signature\Signature;
use Complaints\Domain\Signature\SignerRole;
use DateTimeImmutable;

/**
 * Подписи версий документов: test_complaint_signatures.
 */
interface SignatureRepository
{
    /**
     * Подписи версии по порядку подписания.
     *
     * @return list<Signature>
     */
    public function ofDocument(int $documentId): array;

    /**
     * @throws NotFound
     */
    public function get(int $signatureId): Signature;

    /**
     * Ожидающая подпись подписанта в роли; порядок — SignerRole::signOrder().
     */
    public function request(int $documentId, SignerRole $role, int $signerUserId, DateTimeImmutable $now): int;

    /**
     * Ставит подпись: pending -> signed.
     *
     * @return bool false — подпись уже не ожидает
     */
    public function sign(int $signatureId, int $signatureFileId, string $certificateSubject, DateTimeImmutable $now): bool;

    /**
     * Аннулирует все неаннулированные подписи версии.
     *
     * @return int сколько подписей аннулировано
     */
    public function annulAll(int $documentId, ?int $actorUserId, DateTimeImmutable $now): int;
}
