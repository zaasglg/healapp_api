<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Просмотр списка задач
     */
    public function viewAny(User $user): bool
    {
        // Все аутентифицированные пользователи могут видеть задачи
        // Конкретная фильтрация происходит в контроллере
        return true;
    }

    /**
     * Просмотр конкретной задачи
     */
    public function view(User $user, Task $task): bool
    {
        // Доступ определяется через доступ к пациенту
        // Конкретная проверка в контроллере
        return true;
    }

    /**
     * Создание задач
     * 
     * Правила:
     * - Doctor: МОЖЕТ создавать задачи
     * - Caregiver: НЕ МОЖЕТ создавать задачи
     * - Admin, Owner, Manager: МОГУТ создавать задачи
     * - Client: МОЖЕТ создавать задачи для своих пациентов
     */
    public function create(User $user): bool
    {
        // В Пансионате: Caregiver НЕ МОЖЕТ создавать задачи
        if ($user->organization_id) {
            $organization = $user->organization;
            if ($organization && $organization->isBoardingHouse()) {
                if ($user->hasRole('caregiver')) {
                    return false;
                }
            }
        }
        
        // Doctor, Admin, Owner, Manager, Client могут создавать задачи
        return $user->hasAnyRole(['client', 'manager', 'doctor', 'admin', 'owner']);
    }

    /**
     * Обновление задачи
     */
    public function update(User $user, Task $task): bool
    {
        // В Пансионате: Caregiver НЕ МОЖЕТ обновлять задачи (только выполнять)
        if ($user->organization_id) {
            $organization = $user->organization;
            if ($organization && $organization->isBoardingHouse()) {
                if ($user->hasRole('caregiver')) {
                    return false;
                }
            }
        }
        
        // Doctor, Admin, Owner, Manager, Client могут обновлять задачи
        return $user->hasAnyRole(['client', 'manager', 'doctor', 'admin', 'owner']);
    }

    /**
     * Удаление задачи
     */
    public function delete(User $user, Task $task): bool
    {
        // В Пансионате: Caregiver и Doctor НЕ МОГУТ удалять задачи
        if ($user->organization_id) {
            $organization = $user->organization;
            if ($organization && $organization->isBoardingHouse()) {
                if ($user->hasAnyRole(['caregiver', 'doctor'])) {
                    return false;
                }
            }
        }
        
        // Только Admin, Owner, Manager, Client могут удалять задачи
        return $user->hasAnyRole(['client', 'manager', 'admin', 'owner']);
    }

    /**
     * Выполнение задачи (complete/miss)
     * Caregiver и Doctor могут выполнять задачи
     */
    public function complete(User $user, Task $task): bool
    {
        // Caregiver, Doctor, Admin, Owner, Manager, Client могут выполнять задачи
        return $user->hasAnyRole(['caregiver', 'doctor', 'client', 'manager', 'admin', 'owner']);
    }
}
