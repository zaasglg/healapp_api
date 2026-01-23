<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Просмотр карточки пациента
     */
    public function view(User $user, Patient $patient): bool
    {
        // Владелец карточки (клиент)
        if ($patient->owner_id === $user->id) {
            return true;
        }

        // Создатель карточки
        if ($patient->creator_id === $user->id) {
            return true;
        }

        // Частная сиделка с доступом к дневнику
        if ($user->isPrivateCaregiver()) {
            return $patient->diary && $patient->diary->hasAccess($user);
        }

        // Сотрудник организации пациента
        if ($patient->organization_id && $patient->organization_id === $user->organization_id) {
            $org = $user->organization;

            // Пансионат: все видят всех
            if ($org->isBoardingHouse()) {
                return true;
            }

            // Агентство: нужен доступ к дневнику
            if ($org->isAgency()) {
                return $patient->diary && $patient->diary->hasAccess($user);
            }
        }

        return false;
    }

    /**
     * Создание карточки пациента
     */
    public function create(User $user): bool
    {
        // В Пансионате: только admin, owner и manager могут создавать пациентов
        // Doctor и Caregiver имеют только read-only доступ
        if ($user->organization_id) {
            $organization = $user->organization;
            if ($organization && $organization->isBoardingHouse()) {
                // В Пансионате запрещаем создание для doctor и caregiver
                if ($user->hasAnyRole(['doctor', 'caregiver'])) {
                    return false;
                }
            }
        }

        return $user->canCreatePatients();
    }

    /**
     * Редактирование карточки пациента
     */
    public function edit(User $user, Patient $patient): bool
    {
        // Владелец карточки
        if ($patient->owner_id === $user->id) {
            return true;
        }

        // Частная сиделка-создатель
        if ($user->isPrivateCaregiver() && $patient->creator_id === $user->id) {
            return true;
        }

        // Сотрудник организации
        if ($patient->organization_id && $patient->organization_id === $user->organization_id) {
            $organization = $user->organization;

            // В Пансионате: только admin, owner и manager могут редактировать
            // Doctor и Caregiver имеют только read-only доступ
            if ($organization && $organization->isBoardingHouse()) {
                if ($user->hasAnyRole(['doctor', 'caregiver'])) {
                    return false;
                }
            }

            // Только admin/owner/manager организации могут редактировать
            return $user->hasAnyRole(['owner', 'admin', 'manager'])
                && $patient->organization_id === $user->organization_id;
        }

        return false;
    }

    /**
     * Удаление карточки пациента
     */
    public function delete(User $user, Patient $patient): bool
    {
        // Только владелец карточки или owner организации
        if ($patient->owner_id === $user->id) {
            return true;
        }

        return $user->isOwner() && $patient->organization_id === $user->organization_id;
    }

    /**
     * Создание дневника для пациента
     */
    public function createDiary(User $user, Patient $patient): bool
    {
        return $this->edit($user, $patient);
    }
}
