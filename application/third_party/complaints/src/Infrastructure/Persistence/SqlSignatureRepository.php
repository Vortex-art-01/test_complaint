<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\SignatureRepository;
use Complaints\Domain\Signature\Signature;
use Complaints\Domain\Signature\SignatureStatus;
use Complaints\Domain\Signature\SignerRole;
use DateTimeImmutable;

final class SqlSignatureRepository implements SignatureRepository
{
    private const COLUMNS = 'id, test_complaint_documents_id, signer_role, signer_user_id, sign_order, status,'
        . ' signature_file_id, certificate_subject, signed_at, annulled_at';

    public function __construct(
        private readonly Database $db,
        private readonly SqlValues $values,
    ) {
    }

    public function ofDocument(int $documentId): array
    {
        return array_map(
            fn (array $row): Signature => $this->signature($row),
            $this->db->rows(
                'SELECT ' . self::COLUMNS . ' FROM test_complaint_signatures WHERE test_complaint_documents_id = :document ORDER BY sign_order',
                ['document' => $documentId],
            ),
        );
    }

    public function get(int $signatureId): Signature
    {
        $row = $this->db->row('SELECT ' . self::COLUMNS . ' FROM test_complaint_signatures WHERE id = :id', ['id' => $signatureId]);

        return $row === null ? throw new NotFound() : $this->signature($row);
    }

    public function request(int $documentId, SignerRole $role, int $signerUserId, DateTimeImmutable $now): int
    {
        return $this->values->int($this->db->value(
            'INSERT INTO test_complaint_signatures (test_complaint_documents_id, signer_role, signer_user_id, sign_order, status, created_at)'
            . ' VALUES (:document, :role, :signer, :sign_order, :status, :created) RETURNING id',
            [
                'document' => $documentId,
                'role' => $role,
                'signer' => $signerUserId,
                'sign_order' => $role->signOrder(),
                'status' => SignatureStatus::Pending,
                'created' => $now,
            ],
        ));
    }

    public function sign(int $signatureId, int $signatureFileId, string $certificateSubject, DateTimeImmutable $now): bool
    {
        return $this->db->execute(
            'UPDATE test_complaint_signatures SET status = :signed, signature_file_id = :file, certificate_subject = :subject, signed_at = :now'
            . ' WHERE id = :id AND status = :pending',
            [
                'signed' => SignatureStatus::Signed,
                'file' => $signatureFileId,
                'subject' => mb_substr($certificateSubject, 0, 2000),
                'now' => $now,
                'id' => $signatureId,
                'pending' => SignatureStatus::Pending,
            ],
        ) === 1;
    }

    public function annulAll(int $documentId, ?int $actorUserId, DateTimeImmutable $now): int
    {
        return $this->db->execute(
            'UPDATE test_complaint_signatures SET status = :annulled, annulled_at = :now, annulled_by_user_id = :actor'
            . ' WHERE test_complaint_documents_id = :document AND status <> :already',
            [
                'annulled' => SignatureStatus::Annulled,
                'now' => $now,
                'actor' => $actorUserId,
                'document' => $documentId,
                'already' => SignatureStatus::Annulled,
            ],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function signature(array $row): Signature
    {
        return new Signature(
            $this->values->int($row['id']),
            $this->values->int($row['test_complaint_documents_id']),
            SignerRole::from((string) $row['signer_role']),
            $this->values->int($row['signer_user_id']),
            $this->values->int($row['sign_order']),
            SignatureStatus::from((string) $row['status']),
            $this->values->intOrNull($row['signature_file_id']),
            $this->values->stringOrNull($row['certificate_subject']),
            $this->values->momentOrNull($row['signed_at']),
            $this->values->momentOrNull($row['annulled_at']),
        );
    }
}
