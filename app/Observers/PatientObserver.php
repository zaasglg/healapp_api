<?php

namespace App\Observers;

use App\Models\Diary;
use App\Models\Patient;

class PatientObserver
{
    /**
     * Handle the Patient "created" event.
     * Дневник больше не создаётся автоматически.
     * Дневник должен создаваться вручную через API.
     */
    public function created(Patient $patient): void
    {
        // Дневник не создаётся автоматически
        // Пользователь должен создать дневник вручную при необходимости
    }

    /**
     * Handle the Patient "updated" event.
     * Дневник больше не создаётся автоматически.
     * Дневник должен создаваться вручную через API.
     */
    public function updated(Patient $patient): void
    {
        // Дневник не создаётся автоматически
        // Пользователь должен создать дневник вручную при необходимости
    }
}
