<?php

declare(strict_types=1);

namespace Complaints\Tests\Integration;

use PDO;
use RuntimeException;

/**
 * Тестовая БД: схема complaints_it пересоздаётся один раз за запуск,
 * в неё ставятся заглушки таблиц проекта и миграции модуля 001–004.
 *
 * Подключение берётся из переменных окружения:
 *   COMPLAINTS_TEST_DSN      pgsql:host=127.0.0.1;port=5432;dbname=complaints_it
 *   COMPLAINTS_TEST_USER
 *   COMPLAINTS_TEST_PASSWORD
 * Без COMPLAINTS_TEST_DSN интеграционные тесты пропускаются.
 */
final class TestDatabase
{
    public const SCHEMA = 'complaints_it';

    private const MIGRATIONS = [
        '001_existing_tables_keys',
        '002_buy_complaint',
        '003_app_complaint',
        '004_complaint_documents',
    ];

    private static ?PDO $pdo = null;

    public static function isConfigured(): bool
    {
        return (string) getenv('COMPLAINTS_TEST_DSN') !== '';
    }

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        if (!self::isConfigured()) {
            throw new RuntimeException('COMPLAINTS_TEST_DSN не задана.');
        }

        $user = getenv('COMPLAINTS_TEST_USER');
        $password = getenv('COMPLAINTS_TEST_PASSWORD');
        $pdo = new PDO(
            (string) getenv('COMPLAINTS_TEST_DSN'),
            $user === false ? null : $user,
            $password === false ? null : $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $pdo->exec('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $pdo->exec('CREATE SCHEMA ' . self::SCHEMA);
        $pdo->exec('SET search_path TO ' . self::SCHEMA);
        // Часовой пояс сессии нарочно отличается от часового пояса модуля.
        $pdo->exec("SET TIME ZONE 'UTC'");

        self::runScript($pdo, __DIR__ . '/fixtures/project_tables.sql');
        foreach (self::MIGRATIONS as $migration) {
            self::runScript($pdo, self::migrationsDirectory() . '/' . $migration . '.up.sql');
        }

        return self::$pdo = $pdo;
    }

    /**
     * Каталог database/complaints в корне проекта.
     */
    public static function migrationsDirectory(): string
    {
        return dirname(__DIR__, 5) . '/database/complaints';
    }

    /**
     * Выражения выполняются по одному: CREATE INDEX CONCURRENTLY
     * нельзя выполнять внутри транзакции.
     */
    private static function runScript(PDO $pdo, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Не прочитан файл ' . $path);
        }
        foreach (SqlScript::statements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }
}
