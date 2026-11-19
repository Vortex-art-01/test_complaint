<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Access\Actor;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use DateTimeImmutable;
use LogicException;

/**
 * Документы в памяти: id документа — id файла, как в SQL-репозитории.
 */
final class InMemoryDocuments implements DocumentRepository
{
    /** @var array<int, Document> */
    public array $documents = [];

    public function current(DocumentType $type, int $ownerId): ?Document
    {
        foreach ($this->documents as $document) {
            if ($document->type === $type && $document->ownerId === $ownerId && $document->isCurrent) {
                return $document;
            }
        }

        return null;
    }

    public function get(int $documentId): Document
    {
        return $this->documents[$documentId] ?? throw new NotFound();
    }

    public function create(DocumentType $type, int $ownerId, int $fileId, Actor $generatedBy, DateTimeImmutable $now): Document
    {
        if ($this->current($type, $ownerId) !== null) {
            throw new LogicException('Нарушен уникальный индекс действующей версии.');
        }
        if (isset($this->documents[$fileId])) {
            throw new LogicException('Файл уже привязан к документу.');
        }

        $version = 1;
        foreach ($this->documents as $document) {
            if ($document->type === $type && $document->ownerId === $ownerId) {
                $version = max($version, $document->version + 1);
            }
        }

        return $this->documents[$fileId] = new Document($fileId, $type, $ownerId, $version, true, $fileId, $generatedBy->userId, $now);
    }

    public function retire(int $documentId, DateTimeImmutable $now): void
    {
        $document = $this->documents[$documentId];
        $this->documents[$documentId] = new Document(
            $document->id,
            $document->type,
            $document->ownerId,
            $document->version,
            false,
            $document->fileId,
            $document->generatedByUserId,
            $document->generatedAt,
        );
    }
}
