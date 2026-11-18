<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

/**
 * Проверенная подпись: данные владельца сертификата.
 */
final readonly class VerifiedSignature
{
    /**
     * @param string      $subjectName ФИО владельца сертификата
     * @param string      $iin         ИИН владельца
     * @param string|null $bin         БИН организации; null — сертификат физического лица
     * @param string      $subjectDn   Subject сертификата целиком (до 2000 символов)
     */
    public function __construct(
        public string $subjectName,
        public string $iin,
        public ?string $bin,
        public string $subjectDn,
    ) {
    }
}
