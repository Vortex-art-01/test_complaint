<?php

declare(strict_types=1);

namespace Complaints\Application\View;

use Complaints\Application\Port\FileDescription;
use Complaints\Domain\Document\Document;

/**
 * Действующая версия документа и сведения о её файле.
 */
final readonly class DocumentView
{
    public function __construct(
        public Document $document,
        public FileDescription $file,
    ) {
    }
}
