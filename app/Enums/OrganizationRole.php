<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case DOCTOR = 'doctor';
    case CAREGIVER = 'caregiver';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Владелец',
            self::ADMIN => 'Администратор',
            self::DOCTOR => 'Врач',
            self::CAREGIVER => 'Сиделка',
        };
    }

    /**
     * Права доступа для роли
     */
    public function permissions(): array
    {
        return match ($this) {
            self::OWNER, self::ADMIN => [
                'patients.create',
                'patients.view',
                'patients.edit',
                'patients.delete',
                'diaries.create',
                'diaries.view',
                'diaries.edit',
                'diaries.fill',
                'tasks.create',
                'tasks.view',
                'tasks.edit',
                'tasks.complete',
                'access.manage',
                'employees.invite',
                'employees.manage',
                'clients.invite',
            ],
            self::DOCTOR => [
                'patients.view',
                'diaries.view',
                'diaries.fill',
                'tasks.create',
                'tasks.view',
                'tasks.edit',
            ],
            self::CAREGIVER => [
                'patients.view',
                'diaries.view',
                'diaries.fill',
                'tasks.view',
                'tasks.complete',
            ],
        };
    }

    /**
     * Проверить, может ли роль управлять сотрудниками
     */
    public function canManageEmployees(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    /**
     * Проверить, может ли роль управлять доступом к дневникам
     */
    public function canManageAccess(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    /**
     * Проверить, может ли роль создавать карточки подопечных
     */
    public function canCreatePatients(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    /**
     * Проверить, может ли роль создавать маршрутные листы
     */
    public function canCreateTasks(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::DOCTOR]);
    }

    /**
     * Проверить, может ли роль выполнять задачи
     */
    public function canCompleteTasks(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::CAREGIVER]);
    }

    /**
     * Получить все роли, которые могут быть назначены
     */
    public static function assignableRoles(): array
    {
        return [
            self::ADMIN,
            self::DOCTOR,
            self::CAREGIVER,
        ];
    }
}
