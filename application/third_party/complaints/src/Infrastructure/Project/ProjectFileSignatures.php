<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Project;

use Complaints\Application\Exception\IntegrationFailure;
use Complaints\Application\Port\FileSignature;
use Complaints\Application\Port\FileSignatures;
use Complaints\Application\Port\VerifiedSignature;
use DateTimeImmutable;

/**
 * Подписи ЭЦП у файлов хранилища проекта (fls_stg_entity_id): модуль подписи
 * не хранит, он проверяет их и передаёт проекту.
 */
final class ProjectFileSignatures implements FileSignatures
{
    /**
     * @throws IntegrationFailure
     */
    public function attach(
        int $fileId,
        string $cmsBase64,
        VerifiedSignature $certificate,
        int $signerUserId,
        int $signerOrganizationId,
        DateTimeImmutable $now,
    ): void {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Сохранить у файла $fileId отсоединённую CMS-подпись $cmsBase64 (base64), уже
        // проверенную модулем: подписант $signerUserId из организации $signerOrganizationId,
        // момент подписания $now, Subject сертификата $certificate->subjectDn.
        // Хранилище не приняло подпись — throw new IntegrationFailure(): подписание откатится.
        throw ProjectServiceMissing::in(__METHOD__);
    }

    /**
     * @return list<FileSignature>
     */
    public function of(int $fileId): array
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Вернуть подписи файла $fileId в порядке подписания как list<FileSignature>:
        // new FileSignature(id пользователя-подписанта, момент подписания, Subject сертификата).
        // Подписей нет — []. По ним модуль показывает, кто из маршрута уже подписал.
        throw ProjectServiceMissing::in(__METHOD__);
    }
}
