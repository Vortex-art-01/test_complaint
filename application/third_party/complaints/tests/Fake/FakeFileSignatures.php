<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Port\FileSignature;
use Complaints\Application\Port\FileSignatures;
use Complaints\Application\Port\VerifiedSignature;
use DateTimeImmutable;

/**
 * Подписи у файлов «хранилища проекта» в памяти.
 */
final class FakeFileSignatures implements FileSignatures
{
    /** @var array<int, list<array{signature: FileSignature, cms: string, organizationId: int}>> */
    public array $signatures = [];

    public function attach(
        int $fileId,
        string $cmsBase64,
        VerifiedSignature $certificate,
        int $signerUserId,
        int $signerOrganizationId,
        DateTimeImmutable $now,
    ): void {
        $this->signatures[$fileId][] = [
            'signature' => new FileSignature($signerUserId, $now, $certificate->subjectDn),
            'cms' => $cmsBase64,
            'organizationId' => $signerOrganizationId,
        ];
    }

    public function of(int $fileId): array
    {
        return array_map(static fn (array $entry): FileSignature => $entry['signature'], $this->signatures[$fileId] ?? []);
    }
}
