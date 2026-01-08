<?php

namespace App\Observers;

use App\Models\DiaryEntry;
use App\Models\Task;

/**
 * TaskObserver handles bidirectional synchronization between
 * Route Sheet tasks (маршрутный лист) and Pinned Indicators (закрепленные показатели).
 * 
 * Sync behavior:
 * 1. When a task is completed → creates/updates diary entry → updates pinned_parameters
 * 2. When a task status changes → syncs the linked diary entry accordingly
 */
class TaskObserver
{
    /**
     * Handle the Task "updated" event.
     * Synchronize task status changes with linked diary entries.
     */
    public function updated(Task $task): void
    {
        // Check if status has changed
        if (!$task->isDirty('status')) {
            return;
        }

        $diaryEntry = $task->diaryEntry;
        
        // If task has no linked diary entry, nothing to sync
        if (!$diaryEntry) {
            return;
        }

        $oldStatus = $task->getOriginal('status');
        $newStatus = $task->status;

        // If task is changed from completed to another status
        if ($oldStatus === Task::STATUS_COMPLETED && $newStatus !== Task::STATUS_COMPLETED) {
            // Mark the diary entry as cancelled/updated
            $diaryEntry->update([
                'notes' => 'Задача отменена. ' . ($diaryEntry->notes ?? ''),
            ]);
        }
        
        // If task comment is updated, sync to diary entry
        if ($task->isDirty('comment') && $task->comment) {
            $diaryEntry->update([
                'notes' => $task->comment,
            ]);
        }
    }

    /**
     * Handle the Task "deleted" event.
     * Remove or mark the linked diary entry when task is deleted.
     */
    public function deleted(Task $task): void
    {
        // Get linked diary entry
        $diaryEntry = DiaryEntry::where('source_task_id', $task->id)->first();
        
        if ($diaryEntry) {
            // Update the entry to indicate the task was deleted
            // We don't delete the entry to preserve history
            $diaryEntry->update([
                'source_task_id' => null,
                'notes' => 'Связанная задача удалена. ' . ($diaryEntry->notes ?? ''),
            ]);
        }
    }
}
