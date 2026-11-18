<?php

declare(strict_types=1);

namespace Complaints\Application\Service;

use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Shared\ComplaintSettings;
use Complaints\Application\Shared\UploadedFile;

/**
 * Проверка загружаемого файла: тип, размер, количество.
 */
final class AttachmentRules
{
    public function __construct(
        private readonly ComplaintSettings $settings,
    ) {
    }

    /**
     * @throws ValidationFailed
     */
    public function assertAcceptable(UploadedFile $file, int $alreadyAttached): void
    {
        if ($file->size() === 0) {
            throw ValidationFailed::field('file', 'complaints_error_file_empty');
        }
        if (!$this->settings->isAllowedExtension($file->extension())) {
            throw ValidationFailed::field('file', 'complaints_error_file_type', [
                'types' => implode(', ', $this->settings->attachmentExtensions),
            ]);
        }
        if ($file->size() > $this->settings->attachmentMaxBytes) {
            throw ValidationFailed::field('file', 'complaints_error_file_size', [
                'size' => (string) intdiv($this->settings->attachmentMaxBytes, 1024 * 1024),
            ]);
        }
        if ($alreadyAttached >= $this->settings->attachmentMaxCount) {
            throw ValidationFailed::field('file', 'complaints_error_file_count', [
                'count' => (string) $this->settings->attachmentMaxCount,
            ]);
        }
    }
}
