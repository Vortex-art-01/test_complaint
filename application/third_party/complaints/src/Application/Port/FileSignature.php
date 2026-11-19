<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use DateTimeImmutable;

/**
 * Подпись ЭЦП, привязанная к файлу в хранилище проекта.
 */
final readonly class FileSignature
{
    /**
     * @param string $certificateSubject Subject сертификата подписанта
     */
    public function __construct(
        public int $signerUserId,
        public DateTimeImmutable $signedAt,
        public string $certificateSubject,
    ) {
    }
}
