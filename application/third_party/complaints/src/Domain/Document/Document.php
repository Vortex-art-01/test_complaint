<?php

declare(strict_types=1);

namespace Complaints\Domain\Document;

use Complaints\Domain\Shared\Sha256;
use DateTimeImmutable;

/**
 * Версия сформированного PDF (снимок строки test_complaint_documents).
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
        public Sha256 $sha256,
        public int $generatedByUserId,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
