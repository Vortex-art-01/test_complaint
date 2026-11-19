<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Access\Actor;
use Complaints\Application\Exception\NotFound;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use DateTimeImmutable;

interface DocumentRepository
{
    /**
     * Действующая версия документа владельца; null — не сформирован.
     */
    public function current(DocumentType $type, int $ownerId): ?Document;

    /**
     * Документ по id (id файла).
     *
     * @throws NotFound
     */
    public function get(int $documentId): Document;

    /**
     * Новая действующая версия. Прежнюю версию нужно снять с действия до вызова.
     */
    public function create(DocumentType $type, int $ownerId, int $fileId, Actor $generatedBy, DateTimeImmutable $now): Document;

    /**
     * Версия перестаёт действовать.
     */
    public function retire(int $documentId, DateTimeImmutable $now): void;
}
