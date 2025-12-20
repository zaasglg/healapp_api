<?php

namespace App\Policies;

use App\Models\Diary;
use App\Models\User;

class DiaryPolicy
{
    /**
     * Просмотр дневника
     */
    public function view(User $user, Diary $diary): bool
    {
        return $user->canAccessDiary($diary);
    }

    /**
     * Заполнение записей дневника
     */
    public function fill(User $user, Diary $diary): bool
    {
        // Все, кто могут видеть — могут заполнять
        return $this->view($user, $diary);
    }

    /**
     * Создание дневника
     */
    public function create(User $user): bool
    {
        // Клиенты не создают дневники напрямую
        if ($user->isClient()) {
            return false;
        }

        // Частные сиделки могут создавать
        if ($user->isPrivateCaregiver()) {
            return true;
        }

        // Только owner/admin организации
        return $user->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Редактирование настроек дневника
     */
    public function edit(User $user, Diary $diary): bool
    {
        $patient = $diary->patient;

        // Владелец карточки (клиент)
        if ($patient->owner_id === $user->id) {
            return true;
        }

        // Частная сиделка с доступом
        if ($user->isPrivateCaregiver() && $diary->hasAccess($user, 'edit')) {
            return true;
        }

        // Только admin/owner организации
        return $user->hasAnyRole(['owner', 'admin']) 
            && $patient->organization_id === $user->organization_id;
    }

    /**
     * Управление доступом к дневнику
     */
    public function manageAccess(User $user, Diary $diary): bool
    {
        // Клиент-владелец
        if ($diary->patient->owner_id === $user->id) {
            return true;
        }

        // Owner/Admin организации
        return $user->canManageAccess() 
            && $diary->patient->organization_id === $user->organization_id;
    }

    /**
     * Удаление дневника
     */
    public function delete(User $user, Diary $diary): bool
    {
        // Только владелец карточки или owner организации
        if ($diary->patient->owner_id === $user->id) {
            return true;
        }

        return $user->isOwner() 
            && $diary->patient->organization_id === $user->organization_id;
    }
}
