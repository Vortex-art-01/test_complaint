<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Shared\Sha256;
use DateTimeImmutable;

final class SqlDocumentRepository implements DocumentRepository
{
    private const COLUMNS = 'id, doc_type, test_app_complaint_id, test_complaint_answers_id, test_buy_complaint_id,'
        . ' version, is_current, file_id, sha256, generated_by_user_id, generated_at';

    public function __construct(
        private readonly Database $db,
        private readonly SqlValues $values,
    ) {
    }

    public function current(DocumentType $type, int $ownerId): ?Document
    {
        $row = $this->db->row(
            sprintf('SELECT %s FROM test_complaint_documents WHERE doc_type = :type AND %s = :owner AND is_current', self::COLUMNS, self::ownerColumn($type)),
            ['type' => $type, 'owner' => $ownerId],
        );

        return $row === null ? null : $this->document($row);
    }

    public function get(int $documentId): Document
    {
        $row = $this->db->row('SELECT ' . self::COLUMNS . ' FROM test_complaint_documents WHERE id = :id', ['id' => $documentId]);

        return $row === null ? throw new NotFound() : $this->document($row);
    }

    public function create(DocumentType $type, int $ownerId, int $fileId, Sha256 $sha256, int $userId, DateTimeImmutable $now): Document
    {
        $owner = self::ownerColumn($type);
        $row = $this->db->row(
            sprintf(
                'INSERT INTO test_complaint_documents (doc_type, %1$s, version, is_current, file_id, sha256, generated_by_user_id, generated_at)'
                . ' SELECT CAST(:type AS varchar), CAST(:owner AS bigint), COALESCE(MAX(version), 0) + 1, true,'
                . ' CAST(:file AS bigint), CAST(:sha256 AS varchar), CAST(:user AS bigint), CAST(:now AS timestamptz)'
                . ' FROM test_complaint_documents WHERE %1$s = :owner_versions'
                . ' RETURNING %2$s',
                $owner,
                self::COLUMNS,
            ),
            [
                'type' => $type,
                'owner' => $ownerId,
                'file' => $fileId,
                'sha256' => $sha256->value,
                'user' => $userId,
                'now' => $now,
                'owner_versions' => $ownerId,
            ],
        );

        return $this->document($row ?? throw new NotFound());
    }

    public function retire(int $documentId): void
    {
        $this->db->execute('UPDATE test_complaint_documents SET is_current = false WHERE id = :id', ['id' => $documentId]);
    }

    private static function ownerColumn(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::AppComplaint => 'test_app_complaint_id',
            DocumentType::Answer => 'test_complaint_answers_id',
            DocumentType::ConsolidatedAnswer => 'test_buy_complaint_id',
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function document(array $row): Document
    {
        $type = DocumentType::from((string) $row['doc_type']);

        return new Document(
            $this->values->int($row['id']),
            $type,
            $this->values->int($row[self::ownerColumn($type)]),
            $this->values->int($row['version']),
            $this->values->bool($row['is_current']),
            $this->values->int($row['file_id']),
            Sha256::fromHex((string) $row['sha256']),
            $this->values->int($row['generated_by_user_id']),
            $this->values->moment($row['generated_at']),
        );
    }
}
