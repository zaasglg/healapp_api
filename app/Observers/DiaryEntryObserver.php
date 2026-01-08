<?php

namespace App\Observers;

use App\Models\DiaryEntry;
use App\Models\Task;
use Carbon\Carbon;

/**
 * DiaryEntryObserver handles synchronization between
 * Pinned Indicators (закрепленные показатели) and Route Sheet tasks (маршрутный лист).
 * 
 * Sync behavior:
 * 1. When a diary entry is created → updates pinned_parameters.last_recorded_at
 * 2. When a diary entry is created directly (not from task) → marks matching pending tasks as completed
 */
class DiaryEntryObserver
{
    /**
     * Handle the DiaryEntry "created" event.
     */
    public function created(DiaryEntry $entry): void
    {
        $diary = $entry->diary;
        
        if (!$diary) {
            return;
        }

        // 1. Update pinned_parameters if this entry matches a pinned indicator
        $this->updatePinnedParameters($entry, $diary);
        
        // 2. Sync with Route Sheet tasks if entry was NOT created from a task
        // This handles the case when user records a measurement directly in the diary
        if (!$entry->source_task_id) {
            $this->syncWithPendingTasks($entry, $diary);
        }
    }

    /**
     * Update pinned_parameters.last_recorded_at when a matching entry is created.
     */
    private function updatePinnedParameters(DiaryEntry $entry, $diary): void
    {
        if (empty($diary->pinned_parameters)) {
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

    /**
     * Sync with pending tasks when a diary entry is created directly.
     * If there's a pending task with matching related_diary_key for today,
     * mark it as completed and link the diary entry.
     */
    private function syncWithPendingTasks(DiaryEntry $entry, $diary): void
    {
        $patient = $diary->patient;
        
        if (!$patient) {
            return;
        }

        // Find pending tasks with matching related_diary_key for today
        $today = Carbon::today();
        $matchingTask = Task::where('patient_id', $patient->id)
            ->where('status', Task::STATUS_PENDING)
            ->where('related_diary_key', $entry->key)
            ->whereDate('start_at', $today)
            ->orderBy('start_at', 'asc')
            ->first();

        if ($matchingTask) {
            // Mark the task as completed
            $matchingTask->update([
                'status' => Task::STATUS_COMPLETED,
                'completed_at' => $entry->recorded_at ?? now(),
                'completed_by' => $entry->author_id,
                'comment' => $entry->notes ?? 'Выполнено через дневник',
            ]);
            
            // Link the diary entry to the task
            $entry->update([
                'source_task_id' => $matchingTask->id,
            ]);
        }
    }
}
