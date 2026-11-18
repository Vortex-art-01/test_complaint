<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Port\FileDescription;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Port\StoredFile;

final class FakeFiles implements FileStorage
{
    /** @var array<int, array{name: string, mime: string, bytes: string}> */
    public array $files = [];

    public function store(string $bytes, string $fileName, string $mimeType, int $uploadedByUserId): int
    {
        $id = count($this->files) + 1;
        $this->files[$id] = ['name' => $fileName, 'mime' => $mimeType, 'bytes' => $bytes];

        return $id;
    }

    public function read(int $fileId): StoredFile
    {
        return new StoredFile($this->describe($fileId), $this->files[$fileId]['bytes']);
    }

    public function describe(int $fileId): FileDescription
    {
        $file = $this->files[$fileId] ?? throw new NotFound();

        return new FileDescription($fileId, $file['name'], $file['mime'], strlen($file['bytes']));
    }
}
