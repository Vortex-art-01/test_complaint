<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Exception\IntegrationFailure;
use Complaints\Application\Exception\NotFound;

/**
 * Файловое хранилище проекта.
 */
interface FileStorage
{
    /**
     * Сохраняет байты и возвращает file_id.
     *
     * @throws IntegrationFailure хранилище не приняло файл
     */
    public function store(string $bytes, string $fileName, string $mimeType, int $uploadedByUserId): int;

    /**
     * Содержимое файла.
     *
     * @throws NotFound
     */
    public function read(int $fileId): StoredFile;

    /**
     * Имя, тип и размер файла без чтения содержимого.
     *
     * @throws NotFound
     */
    public function describe(int $fileId): FileDescription;
}
