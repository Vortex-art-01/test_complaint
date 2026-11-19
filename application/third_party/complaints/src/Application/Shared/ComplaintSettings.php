<?php

declare(strict_types=1);

namespace Complaints\Application\Shared;

use DateTimeZone;

/**
 * Настройки модуля из config/complaints.php.
 */
final readonly class ComplaintSettings
{
    /**
     * @param list<int>    $complainableMethodIds  способы закупки, которые можно обжаловать
     * @param list<string> $organizerBins          БИН организаторов (СК-Фармация)
     * @param list<string> $attachmentExtensions   разрешённые расширения вложений
     */
    public function __construct(
        public DateTimeZone $timeZone,
        public int $reviewWorkingDays,
        public int $executionWorkingDays,
        public array $complainableMethodIds,
        public array $organizerBins,
        public int $descriptionMaxLength,
        public int $attachmentMaxBytes,
        public array $attachmentExtensions,
        public int $attachmentMaxCount,
        public int $collectionGraceMinutes,
        public int $pageSize,
    ) {
    }

    public function isAllowedExtension(string $extension): bool
    {
        return in_array(strtolower($extension), $this->attachmentExtensions, true);
    }
}
