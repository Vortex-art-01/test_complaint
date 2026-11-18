<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Repository\TransactionRunner;
use PDO;
use Throwable;

/**
 * Транзакция на соединении проекта. Если проект уже открыл транзакцию
 * (например, через php-activerecord), действие выполняется в SAVEPOINT
 * и откатывается только оно — внешняя транзакция остаётся за проектом.
 */
final class PdoTransactionRunner implements TransactionRunner
{
    private int $depth = 0;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function transactional(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $this->inSavepoint($work);
        }

        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function inSavepoint(callable $work): mixed
    {
        $name = 'complaints_' . ++$this->depth;
        $this->pdo->exec('SAVEPOINT ' . $name);
        try {
            $result = $work();
            $this->pdo->exec('RELEASE SAVEPOINT ' . $name);

            return $result;
        } catch (Throwable $error) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $name);
            throw $error;
        } finally {
            $this->depth--;
        }
    }
}
