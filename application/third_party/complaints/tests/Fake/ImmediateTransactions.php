<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Repository\TransactionRunner;

final class ImmediateTransactions implements TransactionRunner
{
    public function transactional(callable $work): mixed
    {
        return $work();
    }
}
