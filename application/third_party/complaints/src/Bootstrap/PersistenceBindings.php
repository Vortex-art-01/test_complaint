<?php

declare(strict_types=1);

namespace Complaints\Bootstrap;

use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\ComplaintQueries;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Infrastructure\Persistence\Database;
use Complaints\Infrastructure\Persistence\PdoTransactionRunner;
use Complaints\Infrastructure\Persistence\ProjectSchema;
use Complaints\Infrastructure\Persistence\SqlAnswerRepository;
use Complaints\Infrastructure\Persistence\SqlAppComplaintRepository;
use Complaints\Infrastructure\Persistence\SqlAttachmentRepository;
use Complaints\Infrastructure\Persistence\SqlBuyComplaintRepository;
use Complaints\Infrastructure\Persistence\SqlComplaintQueries;
use Complaints\Infrastructure\Persistence\SqlDocumentRepository;
use Complaints\Infrastructure\Persistence\SqlProcurementRepository;
use Complaints\Infrastructure\Persistence\SqlValues;
use DateTimeZone;
use PDO;

/**
 * Хранение модуля в PostgreSQL на PDO-соединении проекта: таблицы trd_complaint*,
 * app_complaint* и справочники ref_* (миграции database/complaints).
 */
final class PersistenceBindings
{
    public static function register(Container $container, PDO $pdo, ProjectSchema $schema, DateTimeZone $timeZone): void
    {
        $container->set(PDO::class, $pdo);
        $container->set(Database::class, new Database($pdo));
        $container->set(SqlValues::class, new SqlValues($timeZone));
        $container->set(ProjectSchema::class, $schema);

        $container->bind(TransactionRunner::class, PdoTransactionRunner::class);
        $container->bind(ProcurementRepository::class, SqlProcurementRepository::class);
        $container->bind(AppComplaintRepository::class, SqlAppComplaintRepository::class);
        $container->bind(BuyComplaintRepository::class, SqlBuyComplaintRepository::class);
        $container->bind(AnswerRepository::class, SqlAnswerRepository::class);
        $container->bind(DocumentRepository::class, SqlDocumentRepository::class);
        $container->bind(AttachmentRepository::class, SqlAttachmentRepository::class);
        $container->bind(ComplaintQueries::class, SqlComplaintQueries::class);
    }
}
