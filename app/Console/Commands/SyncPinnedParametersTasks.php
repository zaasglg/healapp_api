<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Services\TaskService;
use Illuminate\Console\Command;

class SyncPinnedParametersTasks extends Command
{
    protected $signature = 'tasks:sync-pinned {--patient= : Patient ID to sync (optional, syncs all if not provided)}';

    protected $description = 'Sync tasks from pinned parameters in diaries';

    public function handle(): int
    {
        $patientId = $this->option('patient');

        if ($patientId) {
            $patients = Patient::where('id', $patientId)->get();
        } else {
            $patients = Patient::whereHas('diary', function ($q) {
                $q->whereNotNull('pinned_parameters');
            })->get();
        }

        $this->info("Found {$patients->count()} patients with pinned parameters");

        $taskService = new TaskService;
        $totalTasks = 0;

        foreach ($patients as $patient) {
            $diary = $patient->diary;
            if (! $diary || empty($diary->pinned_parameters)) {
                continue;
            }

            $this->info("\nProcessing patient: {$patient->first_name} {$patient->last_name} (ID: {$patient->id})");
            $this->info('Pinned parameters: '.json_encode($diary->pinned_parameters, JSON_UNESCAPED_UNICODE));

            foreach ($diary->pinned_parameters as $param) {
                if (empty($param['times'])) {
                    $this->warn("  - {$param['key']}: no times set, skipping");

                    continue;
                }

                $key = $param['key'];
                $label = $param['label'] ?? $param['key'];

                $this->info("  - Creating/updating template for: {$label}");
                $this->info('    Times: '.implode(', ', $param['times']));

                // Находим или создаём шаблон задачи
                $template = TaskTemplate::firstOrCreate(
                    [
                        'patient_id' => $patient->id,
                        'related_diary_key' => $key,
                    ],
                    [
                        'creator_id' => $patient->creator_id ?? $patient->owner_id ?? 1,
                        'title' => $label,
                        'days_of_week' => null,
                        'time_ranges' => [],
                        'start_date' => now()->format('Y-m-d'),
                        'is_active' => true,
                    ]
                );

                // Обновляем time_ranges
                $timeRanges = [];
                foreach ($param['times'] as $time) {
                    $timeRanges[] = [
                        'start' => $time,
                        'end' => $time,
                    ];
                }

                $template->update([
                    'title' => $label,
                    'time_ranges' => $timeRanges,
                    'is_active' => true,
                ]);

                $this->info("    Template ID: {$template->id}");

                // Удаляем будущие pending задачи
                $deleted = Task::where('template_id', $template->id)
                    ->where('status', 'pending')
                    ->where('start_at', '>=', now())
                    ->delete();

                $this->info("    Deleted {$deleted} future pending tasks");
            }

            // Генерируем задачи
            $generated = $taskService->generateForPatient($patient, 7);
            $totalTasks += $generated;
            $this->info("  Generated {$generated} tasks");
        }

        $this->info("\n✓ Total tasks generated: {$totalTasks}");

        // Показываем задачи на сегодня
        if ($patientId) {
            $todayTasks = Task::where('patient_id', $patientId)
                ->whereDate('start_at', now()->format('Y-m-d'))
                ->orderBy('start_at')
                ->get();

            $this->info("\nTasks for today:");
            foreach ($todayTasks as $task) {
                $this->info("  - {$task->start_at->format('H:i')} {$task->title} ({$task->status})");
            }
        }

        return Command::SUCCESS;
    }
}
