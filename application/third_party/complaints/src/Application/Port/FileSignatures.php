<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Exception\IntegrationFailure;
use DateTimeImmutable;

/**
 * Подписи ЭЦП у файлов хранилища проекта. Модуль проверяет подпись
 * (SignatureVerifier) и передаёт её на хранение; кто и в каком порядке
 * должен подписать, модуль определяет сам по маршруту.
 */
interface FileSignatures
{
    /**
     * Сохраняет проверенную отсоединённую CMS-подпись к файлу.
     *
     * @throws IntegrationFailure
     */
    public function attach(
        int $fileId,
        string $cmsBase64,
        VerifiedSignature $certificate,
        int $signerUserId,
        int $signerOrganizationId,
        DateTimeImmutable $now,
    ): void;

    /**
     * Подписи файла в порядке подписания.
     *
     * @return list<FileSignature>
     */
    public function of(int $fileId): array;
}
