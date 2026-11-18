<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

/**
 * Сведения о файле без содержимого: для списков и ссылок.
 */
final readonly class FileDescription
{
    public function __construct(
        public int $fileId,
        public string $fileName,
        public string $mimeType,
        public int $size,
    ) {
    }
}
