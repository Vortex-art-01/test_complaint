<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

/**
 * Сотрудник: ФИО, ИИН, контакты и должность — для экранов, PDF и проверки ЭЦП.
 */
final readonly class UserCard
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $fullName,
        public string $iin,
        public string $email,
        public string $position,
    ) {
    }
}
