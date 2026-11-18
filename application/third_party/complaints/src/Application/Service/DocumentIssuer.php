<?php

declare(strict_types=1);

namespace Complaints\Application\Service;

use Complaints\Application\Port\FileStorage;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\SignatureRepository;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Shared\Sha256;
use DateTimeImmutable;

/**
 * Версии документов. Любое изменение содержимого — новая версия:
 * прежняя перестаёт действовать, её подписи аннулируются.
 */
final class DocumentIssuer
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly SignatureRepository $signatures,
        private readonly FileStorage $files,
    ) {
    }

    /**
     * Сохраняет PDF и делает его действующей версией документа владельца.
     */
    public function issue(
        DocumentType $type,
        int $ownerId,
        string $pdfBytes,
        string $fileName,
        int $userId,
        DateTimeImmutable $now,
    ): Document {
        $this->retire($type, $ownerId, $userId, $now);
        $fileId = $this->files->store($pdfBytes, $fileName, 'application/pdf', $userId);

        return $this->documents->create($type, $ownerId, $fileId, Sha256::ofBytes($pdfBytes), $userId, $now);
    }

    /**
     * Снимает действующую версию с действия и аннулирует её подписи.
     * Ничего не делает, если документ не сформирован.
     */
    public function retire(DocumentType $type, int $ownerId, ?int $userId, DateTimeImmutable $now): void
    {
        $current = $this->documents->current($type, $ownerId);
        if ($current === null) {
            return;
        }

        $this->signatures->annulAll($current->id, $userId, $now);
        $this->documents->retire($current->id);
    }
}
