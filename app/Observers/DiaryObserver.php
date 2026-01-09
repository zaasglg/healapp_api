<?php

namespace App\Observers;

use App\Models\Diary;

class DiaryObserver
{
    /**
     * Handle the Diary "created" event.
     * Автоматически предоставляет доступ всем сотрудникам пансионата при создании дневника.
     */
    public function created(Diary $diary): void
    {
        $patient = $diary->patient;
        
        if (!$patient || !$patient->organization_id) {
            return;
        }
        
        $organization = $patient->organization;
        
        if (!$organization || !$organization->isBoardingHouse()) {
            return;
        }
        
        // Даём доступ всем сотрудникам организации
        $employees = $organization->employees()->get();
        foreach ($employees as $employee) {
            $diary->grantAccess($employee, 'view');
        }
    }
}
