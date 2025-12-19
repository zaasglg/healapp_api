<?php

namespace App\Observers;

use App\Models\DiaryEntry;

class DiaryEntryObserver
{
    /**
     * Handle the DiaryEntry "created" event.
     */
    public function created(DiaryEntry $entry): void
    {
        $diary = $entry->diary;
        
        if (!$diary || empty($diary->pinned_parameters)) {
            return;
        }

        $pinnedParameters = $diary->pinned_parameters;
        $updated = false;

        foreach ($pinnedParameters as &$param) {
            // Check if this parameter matches the entry key
            if (isset($param['key']) && $param['key'] === $entry->key) {
                // Update last_recorded_at
                $param['last_recorded_at'] = $entry->recorded_at?->toIso8601String() ?? now()->toIso8601String();
                $updated = true;
            }
        }

        if ($updated) {
            $diary->pinned_parameters = $pinnedParameters;
            $diary->save();
        }
    }
}
