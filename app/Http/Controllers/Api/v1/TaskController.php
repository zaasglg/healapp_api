<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\DiaryEntry;
use App\Models\Patient;
use App\Models\Task;
use App\Notifications\TaskMissedNotification;
use App\Notifications\TaskStatusUpdateNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * @OA\Tag(
 *     name="Tasks",
 *     description="API endpoints for managing tasks"
 * )
 */
class TaskController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/tasks",
     *     tags={"Tasks"},
     *     summary="Get tasks for a patient",
     *     description="Retrieve tasks for a specific patient within a date range. Access is restricted based on user role.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="patient_id",
     *         in="query",
     *         required=true,
     *         description="Patient ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         description="Filter tasks from this date (YYYY-MM-DD). Defaults to today.",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         description="Filter tasks to this date (YYYY-MM-DD). Defaults to today.",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tasks retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="patient_id", type="integer", example=1),
     *                 @OA\Property(property="template_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="title", type="string", example="Give medicine"),
     *                 @OA\Property(property="start_at", type="string", format="date-time", example="2024-01-01T09:00:00.000000Z"),
     *                 @OA\Property(property="end_at", type="string", format="date-time", example="2024-01-01T09:30:00.000000Z"),
     *                 @OA\Property(property="status", type="string", example="pending", enum={"pending", "completed", "missed", "cancelled"}),
     *                 @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="completed_by", type="integer", nullable=true, example=2),
     *                 @OA\Property(property="comment", type="string", nullable=true, example="Patient refused"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - patient_id is required",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="patient_id parameter is required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You do not have permission to access this patient.")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $patientId = $request->query('patient_id');

        if (!$patientId) {
            return response()->json([
                'message' => 'patient_id parameter is required',
            ], 400);
        }

        $patient = Patient::findOrFail($patientId);

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'You do not have permission to access this patient.',
            ], 403);
        }

        $fromDate = $request->query('from_date', now()->format('Y-m-d'));
        $toDate = $request->query('to_date', now()->format('Y-m-d'));

        $tasks = Task::where('patient_id', $patientId)
            ->whereDate('start_at', '>=', $fromDate)
            ->whereDate('start_at', '<=', $toDate)
            ->orderBy('start_at', 'asc')
            ->get();

        return response()->json($tasks, 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tasks/{id}/status",
     *     tags={"Tasks"},
     *     summary="Update task status",
     *     description="Update the status of a task. Caregivers can mark tasks as completed or missed. Comment is required when marking as missed. When completing a task with a measurement value, provide 'value' (JSON) and optionally 'completed_at' (actual completion time). If the task has a related_diary_key, a diary entry will be automatically created.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Task ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", example="completed", description="New status", enum={"completed", "missed", "cancelled"}),
     *             @OA\Property(property="comment", type="string", nullable=true, example="Patient refused", description="Required if status is 'missed'"),
     *             @OA\Property(property="value", type="object", nullable=true, description="Measurement value (JSON). Required if task has related_diary_key and status is 'completed'. For blood_pressure: systolic and diastolic. For temperature: value field.", 
     *                 @OA\Property(property="systolic", type="integer", example=120),
     *                 @OA\Property(property="diastolic", type="integer", example=80)
     *             ),
     *             @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example="2024-01-01 14:30:00", description="Actual time when the task was completed (format: Y-m-d H:i:s). If not provided, uses current time.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="patient_id", type="integer", example=1),
     *             @OA\Property(property="template_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="title", type="string", example="Give medicine"),
     *             @OA\Property(property="start_at", type="string", format="date-time", example="2024-01-01T09:00:00.000000Z"),
     *             @OA\Property(property="end_at", type="string", format="date-time", example="2024-01-01T09:30:00.000000Z"),
     *             @OA\Property(property="status", type="string", example="completed"),
     *             @OA\Property(property="completed_at", type="string", format="date-time", example="2024-01-01T09:15:00.000000Z"),
     *             @OA\Property(property="completed_by", type="integer", example=2),
     *             @OA\Property(property="comment", type="string", nullable=true),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You do not have permission to update this task.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Task] {id}")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $patient = $task->patient;

        // Check access - caregivers, managers, clients, and admins can update
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'You do not have permission to update this task.',
            ], 403);
        }

        // Only allow updating pending tasks
        if ($task->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending tasks can be updated.',
            ], 422);
        }

        $data = $request->validated();
        
        // Set completed_by and completed_at if status is completed or missed
        if (in_array($data['status'], ['completed', 'missed'])) {
            $data['completed_by'] = $user->id;
            // Use provided completed_at or default to now()
            $data['completed_at'] = $request->input('completed_at') 
                ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $request->input('completed_at'))
                : now();
        }

        // Remove value from task update (it's not a task field)
        $value = $request->input('value');
        unset($data['value']);

        $task->update($data);
        $task->refresh(); // Reload to get updated relationships

        // If task is completed and has value and related_diary_key, create diary entry
        if ($data['status'] === 'completed' && $value && $task->related_diary_key) {
            $this->createDiaryEntryFromTask($task, $user, $value);
        }

        // Send notifications
        $this->sendTaskNotifications($task, $user);

        return response()->json($task, 200);
    }

    /**
     * Create a diary entry from a completed task with measurement value.
     */
    private function createDiaryEntryFromTask(Task $task, $user, array $value): void
    {
        // Determine diary type based on key
        $physicalKeys = ['temperature', 'blood_pressure', 'pulse', 'weight', 'height', 'blood_sugar'];
        $diaryType = in_array($task->related_diary_key, $physicalKeys) ? 'physical' : 'care';

        DiaryEntry::create([
            'patient_id' => $task->patient_id,
            'author_id' => $user->id,
            'type' => $diaryType,
            'key' => $task->related_diary_key,
            'value' => $value,
            'recorded_at' => $task->completed_at,
            'notes' => 'Created from Task: ' . $task->title,
        ]);
    }

    /**
     * Send notifications when task status is updated.
     */
    private function sendTaskNotifications(Task $task, $user): void
    {
        $patient = $task->patient;
        $recipients = collect();

        // If user is a caregiver/doctor, notify Manager and Client
        if ($user->hasAnyRole(['caregiver', 'doctor'])) {
            // Notify Manager (organization owner)
            if ($patient->organization) {
                $manager = $patient->organization->owner;
                if ($manager && $manager->id !== $user->id) {
                    $recipients->push($manager);
                }
            }

            // Notify Client (patient creator)
            $client = $patient->creator;
            if ($client && $client->id !== $user->id) {
                $recipients->push($client);
            }
        }

        // Send TaskStatusUpdateNotification to all recipients
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TaskStatusUpdateNotification($task));
        }

        // If status is 'missed', send critical notification to Manager
        if ($task->status === 'missed' && $patient->organization) {
            $manager = $patient->organization->owner;
            if ($manager) {
                $manager->notify(new TaskMissedNotification($task));
            }
        }
    }

    /**
     * Check if user can access the patient.
     */
    private function canAccessPatient($user, Patient $patient): bool
    {
        // Admin can access all patients
        if ($user->hasRole('admin')) {
            return true;
        }

        // Client can access their own patients
        if ($user->hasRole('client')) {
            return $patient->creator_id === $user->id;
        }

        // Manager can access patients from their organization
        if ($user->hasRole('manager')) {
            $organization = $user->organization;
            if ($organization && $patient->organization_id === $organization->id) {
                return true;
            }
        }

        // Caregiver/Doctor can access patients they are assigned to
        if ($user->hasAnyRole(['caregiver', 'doctor'])) {
            // Check if user is assigned to this patient
            if ($patient->assignedUsers()->where('user_id', $user->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
