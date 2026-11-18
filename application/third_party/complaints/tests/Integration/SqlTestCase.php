<?php

declare(strict_types=1);

namespace Complaints\Tests\Integration;

use Complaints\Bootstrap\Container;
use Complaints\Bootstrap\PersistenceBindings;
use Complaints\Infrastructure\Persistence\ProjectSchema;
use Complaints\Tests\Support\World;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Каждый тест — в транзакции, которая откатывается после теста.
 * Сценарии модуля внутри неё работают через SAVEPOINT.
 */
abstract class SqlTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        if (!TestDatabase::isConfigured()) {
            self::markTestSkipped('Задайте COMPLAINTS_TEST_DSN, чтобы запустить интеграционные тесты.');
        }
        $this->pdo = TestDatabase::connection();
        $this->pdo->beginTransaction();
        $this->seedProject();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public static function schema(): ProjectSchema
    {
        return ProjectSchema::fromConfig([
            'buy' => [
                'table' => 'test_buy',
                'number' => 'number_anno',
                'name_ru' => 'name_ru',
                'name_kz' => 'name_kz',
                'method' => 'method_id',
                'organizer' => 'organizer_org_id',
                'published_at' => 'published_at',
            ],
            'buy_lot' => ['table' => 'test_buy_lots', 'buy' => 'buy_id', 'number' => 'lot_number', 'name_ru' => 'name_ru', 'name_kz' => 'name_kz'],
            'app' => ['table' => 'test_app', 'supplier' => 'supplier_org_id'],
            'app_lot' => ['table' => 'test_app_lots'],
        ]);
    }

    /**
     * Стенд World на SQL-репозиториях; порты проекта — фейки.
     */
    protected function world(): World
    {
        return new World(function (Container $container): void {
            PersistenceBindings::register($container, $this->pdo, self::schema(), new DateTimeZone('Asia/Almaty'));
        });
    }

    /**
     * Закупка, лоты и заявки из World: те же id и номера.
     */
    private function seedProject(): void
    {
        $this->pdo->exec(<<<'SQL'
            INSERT INTO test_buy (id, number_anno, name_ru, name_kz, method_id, organizer_org_id, published_at)
            VALUES (1, '125407-1', 'Закуп лекарственных средств на 2027 г.', '2027 жылға арналған дәрілік заттарды сатып алу',
                    2, 900, '2026-09-01 10:00:00+05:00')
            SQL);
        $this->pdo->exec(<<<'SQL'
            INSERT INTO test_buy_lots (id, buy_id, lot_number, name_ru, name_kz) VALUES
                (1, 1, '4889720-Т1', 'Амоксициллин, таблетки 500 мг', 'Амоксициллин'),
                (2, 1, '4889721-Т1', 'Цефтриаксон, порошок 1 г', 'Цефтриаксон')
            SQL);
        $this->pdo->exec('INSERT INTO test_app (id, buy_id, supplier_org_id, status_id) VALUES (1, 1, 100, 1), (2, 1, 200, 1)');
        $this->pdo->exec('INSERT INTO test_app_lots (id, test_app_id, test_buy_lots_id, status_id) VALUES (1, 1, 1, 1), (2, 1, 2, 1), (3, 2, 1, 1)');
    }
}
