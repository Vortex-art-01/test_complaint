<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Repository\TransactionManager;

final class ImmediateTransactions implements TransactionManager
{
    public function transactional(callable $work): mixed
    {
        return $work();
    }
}
