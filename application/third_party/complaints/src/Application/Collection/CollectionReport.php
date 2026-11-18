<?php

declare(strict_types=1);

namespace Complaints\Application\Collection;

/**
 * Итог запуска сбора жалоб — для вывода CLI-задачи.
 */
final class CollectionReport
{
    public int $decisionsCreated = 0;
    public int $complaintsCollected = 0;

    /** @var list<string> */
    public array $skipped = [];
}
