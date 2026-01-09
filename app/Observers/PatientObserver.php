<?php

namespace App\Observers;

use App\Models\Diary;
use App\Models\Patient;

class PatientObserver
{
    /**
     * Handle the Patient "created" event.
     * Создаём дневник для пациента в пансионате.
     * DiaryObserver автоматически даст доступ всем сотрудникам.
     */
    public function created(Patient $patient): void
    {
        // Если пациент создан в организации-пансионате, создаём дневник
        if ($patient->organization_id) {
            $organization = $patient->organization;
            
            if ($organization && $organization->isBoardingHouse()) {
                // Создаём дневник для пациента, если его ещё нет
                // DiaryObserver автоматически даст доступ всем сотрудникам
                Diary::firstOrCreate(
                    ['patient_id' => $patient->id],
                    [
                        'pinned_parameters' => [],
                        'settings' => null,
                    ]
                );
            }
        }
    }

    /**
     * Handle the Patient "updated" event.
     * Создаём дневник при перемещении пациента в пансионат.
     * DiaryObserver автоматически даст доступ всем сотрудникам.
     */
    public function updated(Patient $patient): void
    {
        // Если пациента переместили в другую организацию
        if ($patient->isDirty('organization_id') && $patient->organization_id) {
            $organization = $patient->organization;
            
            if ($organization && $organization->isBoardingHouse()) {
                // Создаём дневник, если его нет
                // DiaryObserver автоматически даст доступ всем сотрудникам
                Diary::firstOrCreate(
                    ['patient_id' => $patient->id],
                    [
                        'pinned_parameters' => [],
                        'settings' => null,
                    ]
                );
            }
        }
    }
}
