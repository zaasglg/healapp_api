<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskMissedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Task $task
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $patient = $this->task->patient;
        $completedBy = $this->task->completedBy;

        return [
            'type' => 'task_missed',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'patient_id' => $patient->id,
            'patient_name' => trim($patient->first_name . ' ' . ($patient->middle_name ?? '') . ' ' . $patient->last_name),
            'comment' => $this->task->comment,
            'completed_by' => $completedBy ? [
                'id' => $completedBy->id,
                'name' => trim($completedBy->first_name . ' ' . ($completedBy->middle_name ?? '') . ' ' . $completedBy->last_name),
            ] : null,
            'task_start_at' => $this->task->start_at->toIso8601String(),
        ];
    }
}
