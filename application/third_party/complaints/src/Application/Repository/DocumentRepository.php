<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Exception\NotFound;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Shared\Sha256;
use DateTimeImmutable;

/**
 * Версии сформированных PDF: test_complaint_documents.
 */
interface DocumentRepository
{
    /**
     * Действующая версия документа владельца; null — не сформирован.
     */
    public function current(DocumentType $type, int $ownerId): ?Document;

    /**
     * @throws NotFound
     */
    public function get(int $documentId): Document;

    /**
     * Новая действующая версия: номер = последний + 1.
     * Прежнюю версию нужно снять с действия до вызова.
     */
    public function create(DocumentType $type, int $ownerId, int $fileId, Sha256 $sha256, int $userId, DateTimeImmutable $now): Document;

    /**
     * Версия перестаёт действовать (is_current = false).
     */
    public function retire(int $documentId): void;
}
