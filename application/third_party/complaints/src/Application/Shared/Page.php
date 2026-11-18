<?php

declare(strict_types=1);

namespace Complaints\Application\Shared;

/**
 * Страница списка.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $pageSize,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->pageSize)));
    }

    public static function offset(int $page, int $pageSize): int
    {
        return (max(1, $page) - 1) * $pageSize;
    }
}
