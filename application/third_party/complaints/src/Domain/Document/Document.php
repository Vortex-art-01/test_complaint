<?php

declare(strict_types=1);

namespace Complaints\Domain\Document;

use DateTimeImmutable;

/**
 * Версия сформированного PDF (строка таблиц документов app_complaint_docs, trd_complaint_docs).
 *
 * id документа — id файла в хранилище проекта (fls_stg_entity_id): по нему документ
 * скачивают и подписывают. Действующая версия — не удалённая; номер версии — порядковый
 * среди документов того же вида у того же владельца.
 */
final readonly class Document
{
    public function __construct(
        public int $id,
        public DocumentType $type,
        public int $ownerId,
        public int $version,
        public bool $isCurrent,
        public int $fileId,
        public int $generatedByUserId,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
