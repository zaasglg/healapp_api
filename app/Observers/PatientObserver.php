<?php

namespace App\Observers;

use App\Models\Diary;
use App\Models\Patient;

class PatientObserver
{
    /**
     * Handle the Patient "created" event.
     */
    public function created(Patient $patient): void
    {
        // Если пациент создан в организации-пансионате, создаём дневник и даём доступ всем сотрудникам
        if ($patient->organization_id) {
            $organization = $patient->organization;
            
            if ($organization && $organization->isBoardingHouse()) {
                // Создаём дневник для пациента, если его ещё нет
                $diary = Diary::firstOrCreate(
                    ['patient_id' => $patient->id],
                    [
                        'pinned_parameters' => [],
                        'settings' => null,
                    ]
                );
                
                // Даём доступ всем сотрудникам организации
                $employees = $organization->employees()->get();
                foreach ($employees as $employee) {
                    $diary->grantAccess($employee, 'view');
                }
            }
        }
    }

    /**
     * Handle the Patient "updated" event.
     */
    public function updated(Patient $patient): void
    {
        // Если пациента переместили в другую организацию
        if ($patient->isDirty('organization_id') && $patient->organization_id) {
            $organization = $patient->organization;
            
            if ($organization && $organization->isBoardingHouse()) {
                // Создаём дневник, если его нет
                $diary = Diary::firstOrCreate(
                    ['patient_id' => $patient->id],
                    [
                        'pinned_parameters' => [],
                        'settings' => null,
                    ]
                );
                
                // Даём доступ всем сотрудникам новой организации
                $employees = $organization->employees()->get();
                foreach ($employees as $employee) {
                    $diary->grantAccess($employee, 'view');
                }
            }
        }
    }
}
