<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use PDO;
use PDOStatement;

/**
 * Запросы модуля через PDO-соединение проекта: только подготовленные
 * выражения с именованными параметрами (ключ массива — имя без двоеточия).
 *
 * Значения параметров: int, string, bool, null, DateTimeInterface
 * (передаётся с часовым поясом) и строковые enum (передаётся value).
 * Одно имя параметра в запросе используется один раз — так требует
 * pdo_pgsql без эмуляции подготовленных выражений.
 */
final class Database
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
        if ((int) $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new LogicException('Соединение PDO должно работать в режиме PDO::ERRMODE_EXCEPTION.');
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function rows(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function row(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Первый столбец первой строки; null — строк нет.
     *
     * @param array<string, mixed> $params
     */
    public function value(string $sql, array $params = []): mixed
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_NUM);

        return $row === false ? null : $row[0];
    }

    /**
     * @param array<string, mixed> $params
     * @return int сколько строк затронуто
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Литерал массива PostgreSQL для CAST(:ids AS bigint[]).
     *
     * @param list<int> $ids
     */
    public static function intArray(array $ids): string
    {
        return '{' . implode(',', array_map(static fn (int $id): string => (string) $id, $ids)) . '}';
    }

    /**
     * @param array<string, mixed> $params
     */
    private function run(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            [$bound, $type] = self::bindable($value);
            $statement->bindValue(':' . $name, $bound, $type);
        }
        $statement->execute();

        return $statement;
    }

    /**
     * @return array{0: int|string|bool|null, 1: int}
     */
    private static function bindable(mixed $value): array
    {
        return match (true) {
            $value === null => [null, PDO::PARAM_NULL],
            is_int($value) => [$value, PDO::PARAM_INT],
            is_bool($value) => [$value, PDO::PARAM_BOOL],
            is_string($value) => [$value, PDO::PARAM_STR],
            $value instanceof DateTimeInterface => [$value->format('Y-m-d H:i:s.uP'), PDO::PARAM_STR],
            $value instanceof BackedEnum => [(string) $value->value, PDO::PARAM_STR],
            default => throw new InvalidArgumentException('Неподдерживаемый тип параметра: ' . get_debug_type($value)),
        };
    }
}
