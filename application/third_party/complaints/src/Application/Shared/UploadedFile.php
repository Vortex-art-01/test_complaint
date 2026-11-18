<?php

declare(strict_types=1);

namespace Complaints\Application\Shared;

/**
 * Файл, загруженный пользователем, до сохранения в хранилище проекта.
 */
final readonly class UploadedFile
{
    public function __construct(
        public string $fileName,
        public string $mimeType,
        public string $bytes,
    ) {
    }

    public function size(): int
    {
        return strlen($this->bytes);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->fileName, PATHINFO_EXTENSION));
    }
}
