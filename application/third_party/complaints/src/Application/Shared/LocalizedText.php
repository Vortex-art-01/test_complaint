<?php

declare(strict_types=1);

namespace Complaints\Application\Shared;

/**
 * Текст на двух языках портала: русском и казахском.
 */
final readonly class LocalizedText
{
    public function __construct(
        public string $ru,
        public string $kz,
    ) {
    }

    /**
     * Текст на языке интерфейса; если казахского нет — русский.
     */
    public function in(string $language): string
    {
        return $language === 'kz' && trim($this->kz) !== '' ? $this->kz : $this->ru;
    }
}
