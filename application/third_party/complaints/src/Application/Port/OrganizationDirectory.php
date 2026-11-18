<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Exception\NotFound;

/**
 * Справочник организаций и сотрудников проекта.
 */
interface OrganizationDirectory
{
    /**
     * Реквизиты организации: БИН (ИИН), наименование и юридический адрес на ru и kz.
     *
     * @throws NotFound
     */
    public function organization(int $organizationId): OrganizationCard;

    /**
     * Сотрудник: ФИО, ИИН, e-mail, должность, организация.
     *
     * @throws NotFound
     */
    public function user(int $userId): UserCard;

    /**
     * Сотрудники организации с ролью «Руководитель» — подписанты жалобы.
     *
     * @return list<UserCard>
     */
    public function headsOf(int $organizationId): array;
}
