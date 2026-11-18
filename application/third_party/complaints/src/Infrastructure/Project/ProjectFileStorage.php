<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Project;

use Complaints\Application\Exception\IntegrationFailure;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Port\FileDescription;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Port\StoredFile;

/**
 * Файловое хранилище проекта (то же, что отдаёт /files/download_file/{id}/{check}/).
 * Модуль сам отдаёт свои файлы через контроллер complaint_files с проверкой
 * прав, поэтому ему нужны байты файла, а не ссылка.
 */
final class ProjectFileStorage implements FileStorage
{
    public function store(string $bytes, string $fileName, string $mimeType, int $uploadedByUserId): int
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Сохранить байты в хранилище проекта под именем $fileName с типом $mimeType
        // от имени $uploadedByUserId и вернуть id файла (file_id). Хранилище не приняло
        // файл — throw new IntegrationFailure().
        throw ProjectServiceMissing::in(__METHOD__);
    }

    /**
     * @throws NotFound
     * @throws IntegrationFailure
     */
    public function read(int $fileId): StoredFile
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Прочитать файл хранилища и вернуть new StoredFile($this->describe($fileId), байты).
        // Нет файла — throw new NotFound().
        throw ProjectServiceMissing::in(__METHOD__);
    }

    public function describe(int $fileId): FileDescription
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Вернуть new FileDescription($fileId, исходное имя файла, MIME-тип, размер в байтах)
        // без чтения содержимого. Нет файла — throw new NotFound().
        throw ProjectServiceMissing::in(__METHOD__);
    }
}
