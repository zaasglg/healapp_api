<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Task;
use App\Models\TaskTemplate;
use Carbon\Carbon;

class TaskService
{
    /**
     * Generate tasks for a patient based on active templates.
     *
     * @param Patient $patient
     * @param int $days Number of days to generate tasks for (default: 7)
     * @return int Number of tasks generated
     */
    public function generateForPatient(Patient $patient, int $days = 7): int
    {
        $activeTemplates = TaskTemplate::where('patient_id', $patient->id)
            ->where('is_active', true)
            ->get();

        $generatedCount = 0;
        $today = Carbon::today();
        $endDate = $today->copy()->addDays($days);

        foreach ($activeTemplates as $template) {
            // Check if template is within date range
            if ($template->start_date->isAfter($endDate)) {
                continue;
            }

            if ($template->end_date && $template->end_date->isBefore($today)) {
                continue;
            }

            $startDate = $template->start_date->isAfter($today) 
                ? $template->start_date 
                : $today;

            $effectiveEndDate = $template->end_date 
                ? min($template->end_date, $endDate) 
                : $endDate;

            // Get days of week to generate tasks for
            $daysOfWeek = $template->days_of_week ?? [0, 1, 2, 3, 4, 5, 6]; // If null, every day

            $currentDate = $startDate->copy();
            
            while ($currentDate->lte($effectiveEndDate)) {
                $dayOfWeek = $currentDate->dayOfWeek; // 0 = Sunday, 6 = Saturday

                if (in_array($dayOfWeek, $daysOfWeek)) {
                    // Generate tasks for each time range
                    foreach ($template->time_ranges as $timeRange) {
                        $startTime = Carbon::parse($currentDate->format('Y-m-d') . ' ' . $timeRange['start']);
                        $endTime = Carbon::parse($currentDate->format('Y-m-d') . ' ' . $timeRange['end']);

                        // Check if task already exists
                        $exists = Task::where('patient_id', $patient->id)
                            ->where('template_id', $template->id)
                            ->where('start_at', $startTime)
                            ->exists();

                        if (!$exists) {
                            Task::create([
                                'patient_id' => $patient->id,
                                'template_id' => $template->id,
                                'title' => $template->title,
                                'start_at' => $startTime,
                                'end_at' => $endTime,
                                'status' => 'pending',
                            ]);
                            $generatedCount++;
                        }
                    }
                }

                $currentDate->addDay();
            }
        }

        return $generatedCount;
    }
}

