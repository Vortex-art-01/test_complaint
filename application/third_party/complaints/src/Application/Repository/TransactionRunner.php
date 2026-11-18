<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Throwable;

/**
 * Транзакция сценария.
 */
interface TransactionRunner
{
    /**
     * Выполняет действие в одной транзакции: фиксирует при успехе,
     * откатывает и пробрасывает исключение при ошибке.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     *
     * @throws Throwable исключение действия после отката
     */
    public function transactional(callable $work): mixed;
}
