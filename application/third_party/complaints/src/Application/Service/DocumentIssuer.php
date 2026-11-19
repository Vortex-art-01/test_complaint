<?php

declare(strict_types=1);

namespace Complaints\Application\Service;

use Complaints\Application\Access\Actor;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use DateTimeImmutable;

/**
 * Выпуск новой версии PDF: прежняя версия перестаёт действовать вместе
 * с подписями её файла, новый файл становится действующей версией.
 */
final class DocumentIssuer
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly FileStorage $files,
    ) {
    }

    public function issue(
        DocumentType $type,
        int $ownerId,
        string $pdfBytes,
        string $fileName,
        Actor $generatedBy,
        DateTimeImmutable $now,
    ): Document {
        $this->retire($type, $ownerId, $now);
        $fileId = $this->files->store($pdfBytes, $fileName, 'application/pdf', $generatedBy->userId);

        return $this->documents->create($type, $ownerId, $fileId, $generatedBy, $now);
    }

    /**
     * Снимает действующую версию с действия. Ничего не делает, если документ не сформирован.
     */
    public function retire(DocumentType $type, int $ownerId, DateTimeImmutable $now): void
    {
        $current = $this->documents->current($type, $ownerId);
        if ($current !== null) {
            $this->documents->retire($current->id, $now);
        }
    }
}
