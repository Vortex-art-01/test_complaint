<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\SignatureRepository;
use Complaints\Domain\Signature\Signature;
use Complaints\Domain\Signature\SignatureStatus;
use Complaints\Domain\Signature\SignerRole;
use DateTimeImmutable;
use LogicException;

/**
 * Подписи в памяти; как и в БД, роль и порядок уникальны в пределах версии.
 */
final class InMemorySignatures implements SignatureRepository
{
    /** @var array<int, array<string, mixed>> */
    public array $signatures = [];

    public function ofDocument(int $documentId): array
    {
        $found = [];
        foreach ($this->signatures as $row) {
            if ($row['document_id'] === $documentId) {
                $found[] = $this->get($row['id']);
            }
        }
        usort($found, static fn (Signature $a, Signature $b): int => $a->signOrder <=> $b->signOrder);

        return $found;
    }

    public function get(int $signatureId): Signature
    {
        $row = $this->signatures[$signatureId] ?? throw new NotFound();

        return new Signature(
            $row['id'],
            $row['document_id'],
            $row['role'],
            $row['signer_user_id'],
            $row['sign_order'],
            $row['status'],
            $row['file_id'],
            $row['subject'],
            $row['signed_at'],
            $row['annulled_at'],
        );
    }

    public function request(int $documentId, SignerRole $role, int $signerUserId, DateTimeImmutable $now): int
    {
        foreach ($this->signatures as $row) {
            if ($row['document_id'] === $documentId && ($row['role'] === $role || $row['sign_order'] === $role->signOrder())) {
                throw new LogicException('Нарушен уникальный ключ подписи версии (роль или порядок).');
            }
        }

        $id = count($this->signatures) + 1;
        $this->signatures[$id] = [
            'id' => $id,
            'document_id' => $documentId,
            'role' => $role,
            'signer_user_id' => $signerUserId,
            'sign_order' => $role->signOrder(),
            'status' => SignatureStatus::Pending,
            'file_id' => null,
            'subject' => null,
            'signed_at' => null,
            'annulled_at' => null,
        ];

        return $id;
    }

    public function sign(int $signatureId, int $signatureFileId, string $certificateSubject, DateTimeImmutable $now): bool
    {
        if ($this->signatures[$signatureId]['status'] !== SignatureStatus::Pending) {
            return false;
        }

        $this->signatures[$signatureId]['status'] = SignatureStatus::Signed;
        $this->signatures[$signatureId]['file_id'] = $signatureFileId;
        $this->signatures[$signatureId]['subject'] = $certificateSubject;
        $this->signatures[$signatureId]['signed_at'] = $now;

        return true;
    }

    public function annulAll(int $documentId, ?int $actorUserId, DateTimeImmutable $now): int
    {
        $count = 0;
        foreach ($this->signatures as $id => $row) {
            if ($row['document_id'] === $documentId && $row['status'] !== SignatureStatus::Annulled) {
                $this->signatures[$id]['status'] = SignatureStatus::Annulled;
                $this->signatures[$id]['annulled_at'] = $now;
                $count++;
            }
        }

        return $count;
    }
}
