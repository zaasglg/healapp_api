<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Просмотр организации
     */
    public function view(User $user, Organization $organization): bool
    {
        // Любой сотрудник организации может просматривать
        return $user->isEmployeeOf($organization);
    }

    /**
     * Редактирование организации
     */
    public function edit(User $user, Organization $organization): bool
    {
        // Только owner/admin
        return $user->hasOrganizationRole($organization, [
            OrganizationRole::OWNER,
            OrganizationRole::ADMIN,
        ]);
    }

    /**
     * Приглашение сотрудников
     */
    public function inviteEmployee(User $user, Organization $organization): bool
    {
        // Только owner/admin
        return $user->hasOrganizationRole($organization, [
            OrganizationRole::OWNER,
            OrganizationRole::ADMIN,
        ]);
    }

    /**
     * Управление сотрудниками
     */
    public function manageEmployees(User $user, Organization $organization): bool
    {
        // Только owner/admin
        return $user->hasOrganizationRole($organization, [
            OrganizationRole::OWNER,
            OrganizationRole::ADMIN,
        ]);
    }

    /**
     * Изменение роли сотрудника
     */
    public function changeEmployeeRole(User $user, Organization $organization): bool
    {
        // Только owner может менять роли (в том числе назначать admin)
        return $user->hasOrganizationRole($organization, OrganizationRole::OWNER);
    }

    /**
     * Удаление сотрудника
     */
    public function removeEmployee(User $user, Organization $organization): bool
    {
        // Owner и admin могут удалять сотрудников
        // Но admin не может удалить owner или другого admin
        return $user->hasOrganizationRole($organization, [
            OrganizationRole::OWNER,
            OrganizationRole::ADMIN,
        ]);
    }

    /**
     * Приглашение клиентов
     */
    public function inviteClient(User $user, Organization $organization): bool
    {
        // Owner/Admin могут приглашать клиентов
        return $user->hasOrganizationRole($organization, [
            OrganizationRole::OWNER,
            OrganizationRole::ADMIN,
        ]);
    }

    /**
     * Удаление организации
     */
    public function delete(User $user, Organization $organization): bool
    {
        // Только owner может удалить организацию
        return $user->hasOrganizationRole($organization, OrganizationRole::OWNER);
    }

    /**
     * Просмотр всех пациентов организации
     */
    public function viewPatients(User $user, Organization $organization): bool
    {
        // Все сотрудники могут видеть пациентов (с учетом специфики агентства/пансионата)
        return $user->isEmployeeOf($organization);
    }

    /**
     * Создание пациентов для организации
     */
    public function createPatient(User $user, Organization $organization): bool
    {
        // Только owner/admin
        return $user->hasOrganizationRole($organization, [
            OrganizationRole::OWNER,
            OrganizationRole::ADMIN,
        ]);
    }

    /**
     * Управление доступом к дневникам
     */
    public function manageDiaryAccess(User $user, Organization $organization): bool
    {
        // Только owner/admin
        return $user->hasOrganizationRole($organization, [
            OrganizationRole::OWNER,
            OrganizationRole::ADMIN,
        ]);
    }
}
