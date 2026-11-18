<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

/**
 * Файл из хранилища проекта вместе с содержимым.
 */
final readonly class StoredFile
{
    public function __construct(
        public FileDescription $description,
        public string $bytes,
    ) {
    }
}
