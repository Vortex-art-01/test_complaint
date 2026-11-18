<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Shared\Sha256;
use DateTimeImmutable;
use LogicException;

/**
 * Версии документов в памяти; как и в БД, у владельца одна действующая версия.
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

    public function create(DocumentType $type, int $ownerId, int $fileId, Sha256 $sha256, int $userId, DateTimeImmutable $now): Document
    {
        if ($this->current($type, $ownerId) !== null) {
            throw new LogicException('Нарушен уникальный индекс действующей версии.');
        }

        $version = 1;
        foreach ($this->documents as $document) {
            if ($document->type === $type && $document->ownerId === $ownerId) {
                $version = max($version, $document->version + 1);
            }
        }

        $id = count($this->documents) + 1;

        return $this->documents[$id] = new Document($id, $type, $ownerId, $version, true, $fileId, $sha256, $userId, $now);
    }

    public function retire(int $documentId): void
    {
        $document = $this->documents[$documentId];
        $this->documents[$documentId] = new Document(
            $document->id,
            $document->type,
            $document->ownerId,
            $document->version,
            false,
            $document->fileId,
            $document->sha256,
            $document->generatedByUserId,
            $document->generatedAt,
        );
    }
}
