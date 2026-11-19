<?php

declare(strict_types=1);

namespace Complaints\Application\Service;

use Complaints\Application\Access\Actor;
use Complaints\Application\Exception\IntegrationFailure;
use Complaints\Application\Exception\SignatureRejected;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\FileSignature;
use Complaints\Application\Port\FileSignatures;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\SignatureVerifier;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Signature\Signature;
use Complaints\Domain\Signature\SignatureStatus;
use Complaints\Domain\Signature\SignerRole;
use DateTimeImmutable;

/**
 * Подписание документов ЭЦП. Подписи хранятся у файлов в хранилище проекта,
 * а кто и в каком порядке подписывает, модуль определяет по маршруту:
 * жалобу — выбранный руководитель, сводный документ — рассматривающий,
 * согласующий (если назначен) и утверждающий решения.
 *
 * Новая версия документа — новый файл без подписей, поэтому при отзыве
 * или возврате в черновик достаточно снять версию с действия.
 */
final class SignatureService
{
    public function __construct(
        private readonly FileSignatures $fileSignatures,
        private readonly FileStorage $files,
        private readonly SignatureVerifier $verifier,
        private readonly OrganizationDirectory $directory,
        private readonly AppComplaintRepository $complaints,
        private readonly BuyComplaintRepository $decisions,
    ) {
    }

    /**
     * Подписанты документа по порядку подписания.
     *
     * @return list<array{0: SignerRole, 1: int}> роль и пользователь
     */
    public function route(Document $document): array
    {
        if ($document->type === DocumentType::AppComplaint) {
            return [[SignerRole::Head, $this->complaints->get($document->ownerId)->headUserId]];
        }
        if ($document->type !== DocumentType::ConsolidatedAnswer) {
            return [];
        }

        $decision = $this->decisions->get($document->ownerId);
        $route = [];
        foreach ([
            [SignerRole::Reviewer, $decision->reviewerUserId],
            [SignerRole::Coordinator, $decision->coordinatorUserId],
            [SignerRole::Approver, $decision->approverUserId],
        ] as [$role, $userId]) {
            if ($userId !== null) {
                $route[] = [$role, $userId];
            }
        }

        return $route;
    }

    /**
     * Подписи маршрута: поставленные и ожидаемые.
     *
     * @return list<Signature>
     */
    public function signatures(Document $document): array
    {
        $signed = $this->signedByUser($document);

        return array_map(
            static function (array $step) use ($signed): Signature {
                [$role, $userId] = $step;
                $signature = $signed[$userId] ?? null;

                return new Signature(
                    $role,
                    $userId,
                    $signature === null ? SignatureStatus::Pending : SignatureStatus::Signed,
                    $signature?->certificateSubject,
                    $signature?->signedAt,
                );
            },
            $this->route($document),
        );
    }

    public function isFullySigned(Document $document): bool
    {
        $signatures = $this->signatures($document);
        foreach ($signatures as $signature) {
            if (!$signature->isSigned()) {
                return false;
            }
        }

        return $signatures !== [];
    }

    /**
     * Очередь подписания дошла до пользователя.
     */
    public function isAwaitedFrom(Document $document, int $userId): bool
    {
        return $document->isCurrent && $this->awaited($document)?->signerUserId === $userId;
    }

    /**
     * К файлу уже приложена хотя бы одна подпись.
     */
    public function hasSignatures(Document $document): bool
    {
        return $this->fileSignatures->of($document->fileId) !== [];
    }

    /**
     * Проверяет подпись участника маршрута над действующей версией и сохраняет её у файла.
     *
     * @param string $organizationIdentifier БИН организации подписанта (у ИП — ИИН)
     *
     * @throws SignatureRejected
     * @throws StatusConflict подпись не ожидается от пользователя или версия не действует
     * @throws IntegrationFailure
     */
    public function sign(
        Document $document,
        SignerRole $role,
        Actor $signer,
        string $cmsBase64,
        string $organizationIdentifier,
        DateTimeImmutable $now,
    ): void {
        $awaited = $document->isCurrent ? $this->awaited($document) : null;
        if ($awaited === null || $awaited->role !== $role || $awaited->signerUserId !== $signer->userId) {
            throw new StatusConflict('complaints_error_signature_not_expected');
        }

        $cms = base64_decode($cmsBase64, true);
        if ($cms === false || $cms === '') {
            throw new SignatureRejected('complaints_error_signature_format');
        }

        $verified = $this->verifier->verify($this->files->read($document->fileId)->bytes, $cmsBase64);
        if ($verified->iin !== $this->directory->user($signer->userId)->iin) {
            throw new SignatureRejected('complaints_error_signature_iin');
        }
        if (($verified->bin ?? $verified->iin) !== $organizationIdentifier) {
            throw new SignatureRejected('complaints_error_signature_bin');
        }

        $this->fileSignatures->attach($document->fileId, $cmsBase64, $verified, $signer->userId, $signer->organizationId, $now);
    }

    /**
     * Первая подпись маршрута, которая ещё не поставлена.
     */
    private function awaited(Document $document): ?Signature
    {
        foreach ($this->signatures($document) as $signature) {
            if (!$signature->isSigned()) {
                return $signature;
            }
        }

        return null;
    }

    /**
     * @return array<int, FileSignature> пользователь => его первая подпись файла
     */
    private function signedByUser(Document $document): array
    {
        $signed = [];
        foreach ($this->fileSignatures->of($document->fileId) as $signature) {
            $signed[$signature->signerUserId] ??= $signature;
        }

        return $signed;
    }
}
