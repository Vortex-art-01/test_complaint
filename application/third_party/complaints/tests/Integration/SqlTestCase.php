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

    /**
     * Имена таблиц и столбцов проекта — как в config/complaints.php.
     *
     * @return array<string, array<string, string>>
     */
    public static function schemaConfig(): array
    {
        return [
            'buy' => [
                'table' => 'trd_buy',
                'number' => 'number_anno',
                'name_ru' => 'name_ru',
                'name_kz' => 'name_kz',
                'method' => 'method_id',
                'organizer' => 'organizer_org_id',
                'published_at' => 'published_at',
                'appeal_start' => 'appeal_start_date',
                'appeal_end' => 'appeal_end_date',
            ],
            'buy_lot' => ['table' => 'trd_buy_lot', 'buy' => 'trd_buy_id', 'number' => 'lot_number', 'name_ru' => 'name_ru', 'name_kz' => 'name_kz'],
            'app' => ['table' => 'trd_app', 'buy' => 'trd_buy_id', 'supplier' => 'supplier_org_id'],
            'app_lot' => ['table' => 'trd_app_lot', 'app' => 'trd_app_id', 'buy_lot' => 'trd_buy_lot_id'],
        ];
    }

    public static function schema(): ProjectSchema
    {
        return ProjectSchema::fromConfig(self::schemaConfig());
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
     * Проект установил закупке новый срок обжалования (новый протокол итогов).
     */
    protected function setAppealDays(string $firstDay, string $lastDay): void
    {
        $statement = $this->pdo->prepare('UPDATE trd_buy SET appeal_start_date = :first, appeal_end_date = :last WHERE id = 1');
        $statement->execute(['first' => $firstDay, 'last' => $lastDay]);
    }

    /**
     * Закупка, лоты и заявки из World: те же id, номера и срок обжалования 15–19.10.
     */
    private function seedProject(): void
    {
        $this->pdo->exec(<<<'SQL'
            INSERT INTO trd_buy (id, number_anno, name_ru, name_kz, method_id, organizer_org_id, published_at,
                                 appeal_start_date, appeal_end_date)
            VALUES (1, '125407-1', 'Закуп лекарственных средств на 2027 г.', '2027 жылға арналған дәрілік заттарды сатып алу',
                    2, 900, '2026-09-01 10:00:00+05:00', '2026-10-15', '2026-10-19')
            SQL);
        $this->pdo->exec(<<<'SQL'
            INSERT INTO trd_buy_lot (id, trd_buy_id, lot_number, name_ru, name_kz) VALUES
                (1, 1, '4889720-Т1', 'Амоксициллин, таблетки 500 мг', 'Амоксициллин'),
                (2, 1, '4889721-Т1', 'Цефтриаксон, порошок 1 г', 'Цефтриаксон')
            SQL);
        $this->pdo->exec('INSERT INTO trd_app (id, trd_buy_id, supplier_org_id, status_id) VALUES (1, 1, 100, 1), (2, 1, 200, 1)');
        $this->pdo->exec('INSERT INTO trd_app_lot (id, trd_app_id, trd_buy_lot_id, status_id) VALUES (1, 1, 1, 1), (2, 1, 2, 1), (3, 2, 1, 1)');
    }
}
