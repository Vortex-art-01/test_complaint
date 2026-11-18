<?php

declare(strict_types=1);

namespace Complaints\Application\Service;

use Complaints\Application\Exception\SignatureRejected;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\SignatureVerifier;
use Complaints\Application\Repository\SignatureRepository;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Shared\Sha256;
use Complaints\Domain\Signature\Signature;
use Complaints\Domain\Signature\SignerRole;
use DateTimeImmutable;

/**
 * Маршрут подписания версии документа и проверка подписей.
 */
final class SignatureService
{
    public function __construct(
        private readonly SignatureRepository $signatures,
        private readonly FileStorage $files,
        private readonly SignatureVerifier $verifier,
        private readonly OrganizationDirectory $directory,
    ) {
    }

    /**
     * Создаёт ожидающие подписи маршрута.
     *
     * @param array<value-of<SignerRole>, int> $route роль => пользователь
     */
    public function requestRoute(Document $document, array $route, DateTimeImmutable $now): void
    {
        foreach ($route as $role => $userId) {
            $this->signatures->request($document->id, SignerRole::from($role), $userId, $now);
        }
    }

    /**
     * Проверяет подпись пользователя над действующей версией и ставит её.
     *
     * @param string $organizationIdentifier БИН организации подписанта (у ИП — ИИН)
     *
     * @throws SignatureRejected
     * @throws StatusConflict подпись не ожидается или ещё не подписаны предыдущие
     */
    public function sign(
        Document $document,
        SignerRole $role,
        int $userId,
        string $cmsBase64,
        string $organizationIdentifier,
        DateTimeImmutable $now,
    ): Signature {
        $signature = $this->awaitedSignature($document->id, $role, $userId);
        $cms = base64_decode($cmsBase64, true);
        if ($cms === false || $cms === '') {
            throw new SignatureRejected('complaints_error_signature_format');
        }

        $bytes = $this->files->read($document->fileId)->bytes;
        if (!Sha256::ofBytes($bytes)->equals($document->sha256)) {
            throw new SignatureRejected('complaints_error_document_changed');
        }

        $verified = $this->verifier->verify($bytes, $cmsBase64);
        if ($verified->iin !== $this->directory->user($userId)->iin) {
            throw new SignatureRejected('complaints_error_signature_iin');
        }
        if (($verified->bin ?? $verified->iin) !== $organizationIdentifier) {
            throw new SignatureRejected('complaints_error_signature_bin');
        }

        $fileId = $this->files->store(
            $cms,
            sprintf('signature_%d_%s.p7s', $document->id, $role->value),
            'application/pkcs7-signature',
            $userId,
        );
        if (!$this->signatures->sign($signature->id, $fileId, mb_substr($verified->subjectDn, 0, 2000), $now)) {
            throw new StatusConflict();
        }

        return $this->signatures->get($signature->id);
    }

    /**
     * Все подписи маршрута поставлены.
     */
    public function isFullySigned(Document $document): bool
    {
        $signatures = $this->signatures->ofDocument($document->id);
        foreach ($signatures as $signature) {
            if (!$signature->isSigned()) {
                return false;
            }
        }

        return $signatures !== [];
    }

    /**
     * Очередь подписания дошла до пользователя: первая не поставленная подпись — его и ожидает.
     */
    public function isAwaitedFrom(int $documentId, int $userId): bool
    {
        foreach ($this->signatures->ofDocument($documentId) as $signature) {
            if (!$signature->isSigned()) {
                return $signature->isPending() && $signature->signerUserId === $userId;
            }
        }

        return false;
    }

    /**
     * Ожидающая подпись пользователя в роли; все подписи с меньшим порядком поставлены.
     *
     * @throws StatusConflict
     */
    public function awaitedSignature(int $documentId, SignerRole $role, int $userId): Signature
    {
        foreach ($this->signatures->ofDocument($documentId) as $signature) {
            if ($signature->role === $role) {
                if ($signature->isPending() && $signature->signerUserId === $userId) {
                    return $signature;
                }
                break;
            }
            if (!$signature->isSigned()) {
                break;
            }
        }

        throw new StatusConflict('complaints_error_signature_not_expected');
    }
}
