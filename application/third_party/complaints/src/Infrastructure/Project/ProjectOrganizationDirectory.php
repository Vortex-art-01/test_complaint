<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Project;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Port\OrganizationCard;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\UserCard;

/**
 * Справочник организаций и сотрудников проекта.
 */
final class ProjectOrganizationDirectory implements OrganizationDirectory
{
    /** @var array<int, OrganizationCard> */
    private array $organizations = [];

    /** @var array<int, UserCard> */
    private array $users = [];

    public function organization(int $organizationId): OrganizationCard
    {
        return $this->organizations[$organizationId] ??= $this->loadOrganization($organizationId);
    }

    public function user(int $userId): UserCard
    {
        return $this->users[$userId] ??= $this->loadUser($userId);
    }

    public function headsOf(int $organizationId): array
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Вернуть сотрудников организации с ролью «Руководитель» (подписанты жалобы)
        // как list<UserCard>, по ФИО. Пустой список — в организации нет руководителя,
        // жалобу создать нельзя (ошибка complaints_error_head_not_found).
        throw ProjectServiceMissing::in(__METHOD__);
    }

    /**
     * @throws NotFound
     */
    private function loadOrganization(int $organizationId): OrganizationCard
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Прочитать организацию проекта и вернуть new OrganizationCard(
        //     id, БИН (у ИП — ИИН), new LocalizedText(наименование ru, наименование kz),
        //     new LocalizedText(юридический адрес ru, юридический адрес kz)).
        // Нет организации — throw new NotFound().
        throw ProjectServiceMissing::in(__METHOD__);
    }

    /**
     * @throws NotFound
     */
    private function loadUser(int $userId): UserCard
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Прочитать пользователя проекта и вернуть new UserCard(
        //     id, id организации, ФИО, ИИН (12 цифр — сверяется с сертификатом ЭЦП),
        //     e-mail, должность). Нет пользователя — throw new NotFound().
        throw ProjectServiceMissing::in(__METHOD__);
    }
}
