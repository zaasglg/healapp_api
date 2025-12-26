<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DiaryEntry;
use App\Models\Patient;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Notifications\TaskMissedNotification;
use App\Notifications\TaskStatusUpdateNotification;
use App\Services\TaskService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Route Sheet",
 *     description="API endpoints for managing route sheet (маршрутный лист)"
 * )
 */
class RouteSheetController extends Controller
{
    protected TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/route-sheet",
     *     tags={"Route Sheet"},
     *     summary="Get route sheet (tasks) for a patient or current user",
     *     description="Get all tasks for a specific date. For caregivers in nursing homes, returns only tasks assigned to them.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="patient_id",
     *         in="query",
     *         required=false,
     *         description="Patient ID (optional for caregivers who see only their assigned tasks)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=false,
     *         description="Date to get tasks for (YYYY-MM-DD). Defaults to today.",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         description="Start date for range (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         description="End date for range (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"pending", "completed", "missed", "cancelled"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Route sheet retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="tasks", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="start_at", type="string", format="date-time"),
     *                 @OA\Property(property="end_at", type="string", format="date-time"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="priority", type="integer"),
     *                 @OA\Property(property="is_rescheduled", type="boolean"),
     *                 @OA\Property(property="is_overdue", type="boolean"),
     *                 @OA\Property(property="patient", type="object"),
     *                 @OA\Property(property="assigned_to", type="object", nullable=true)
     *             )),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="pending", type="integer"),
     *                 @OA\Property(property="completed", type="integer"),
     *                 @OA\Property(property="missed", type="integer"),
     *                 @OA\Property(property="overdue", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Determine date range
        $date = $request->query('date', now()->format('Y-m-d'));
        $fromDate = $request->query('from_date', $date);
        $toDate = $request->query('to_date', $date);
        
        $query = Task::with(['patient', 'assignedTo', 'template'])
            ->whereDate('start_at', '>=', $fromDate)
            ->whereDate('start_at', '<=', $toDate);
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }
        
        // Filter by patient if provided
        if ($request->has('patient_id')) {
            $patient = Patient::findOrFail($request->query('patient_id'));
            
            if (!$this->canAccessPatient($user, $patient)) {
                return response()->json([
                    'message' => 'У вас нет доступа к этому пациенту.',
                ], 403);
            }
            
            $query->where('patient_id', $patient->id);
        }
        
        // For caregivers: only show tasks assigned to them
        if ($user->hasRole('caregiver')) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereNull('assigned_to');
            });
            
            // If no patient_id specified, filter by assigned patients
            if (!$request->has('patient_id')) {
                $assignedPatientIds = $user->assignedPatients()->pluck('patients.id');
                $query->whereIn('patient_id', $assignedPatientIds);
            }
        }
        
        // Если patient_id не указан, фильтруем по доступу пользователя
        if (!$request->has('patient_id')) {
            // Клиент: только свои пациенты
            if ($user->isClient()) {
                $query->whereHas('patient', function ($q) use ($user) {
                    $q->where('owner_id', $user->id);
                });
            }
            // Частная сиделка: только назначенные пациенты
            elseif ($user->isPrivateCaregiver()) {
                $assignedPatientIds = $user->assignedPatients()->pluck('patients.id');
                $query->whereIn('patient_id', $assignedPatientIds);
            }
            // Сотрудник организации
            elseif ($user->organization_id) {
                $organization = $user->organization;
                if ($organization) {
                    // Пансионат: все пациенты организации
                    if ($organization->isBoardingHouse()) {
                        $query->whereHas('patient', function ($q) use ($organization) {
                            $q->where('organization_id', $organization->id);
                        });
                    }
                    // Агентство: только назначенные пациенты (для сиделок/врачей)
                    elseif ($organization->isAgency()) {
                        if ($user->hasAnyRole(['owner', 'admin'])) {
                            // Владельцы и админы видят всех пациентов организации
                            $query->whereHas('patient', function ($q) use ($organization) {
                                $q->where('organization_id', $organization->id);
                            });
                        } else {
                            // Остальные сотрудники видят только назначенных пациентов
                            $assignedPatientIds = $user->assignedPatients()
                                ->where('organization_id', $organization->id)
                                ->pluck('patients.id');
                            $query->whereIn('patient_id', $assignedPatientIds);
                        }
                    }
                }
            }
        }
        
        $tasks = $query->orderBy('start_at', 'asc')
            ->orderBy('priority', 'desc')
            ->get()
            ->map(function ($task) {
                return array_merge($task->toArray(), [
                    'is_rescheduled' => $task->isRescheduled(),
                    'is_overdue' => $task->isOverdue(),
                ]);
            });
        
        // Summary statistics
        $summary = [
            'total' => $tasks->count(),
            'pending' => $tasks->where('status', 'pending')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'missed' => $tasks->where('status', 'missed')->count(),
            'overdue' => $tasks->where('is_overdue', true)->count(),
        ];
        
        return response()->json([
            'date' => $date,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'tasks' => $tasks->values(),
            'summary' => $summary,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/route-sheet/{id}",
     *     tags={"Route Sheet"},
     *     summary="Get a specific task",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Task retrieved successfully"),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Task not found")
     * )
     */
    public function show(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $patient = $task->patient;
        
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этой задаче.',
            ], 403);
        }
        
        $task->load(['patient', 'assignedTo', 'completedBy', 'rescheduledBy', 'template']);
        
        return response()->json(array_merge($task->toArray(), [
            'is_rescheduled' => $task->isRescheduled(),
            'is_overdue' => $task->isOverdue(),
        ]));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/route-sheet",
     *     tags={"Route Sheet"},
     *     summary="Create a single task (without template)",
     *     description="Create a one-time task directly to the route sheet",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"patient_id", "title", "start_at", "end_at"},
     *             @OA\Property(property="patient_id", type="integer"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="start_at", type="string", format="date-time", example="2024-01-01 09:00:00"),
     *             @OA\Property(property="end_at", type="string", format="date-time", example="2024-01-01 09:30:00"),
     *             @OA\Property(property="assigned_to", type="integer", nullable=true, description="User ID of assigned caregiver"),
     *             @OA\Property(property="priority", type="integer", example=0),
     *             @OA\Property(property="related_diary_key", type="string", nullable=true, example="blood_pressure")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Task created successfully"),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Only clients, managers, doctors, admins, owners can create tasks
        if (!$user->hasAnyRole(['client', 'manager', 'doctor', 'admin', 'owner'])) {
            return response()->json([
                'message' => 'У вас нет прав на создание задач.',
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'title' => 'required|string|max:255',
            'start_at' => 'required|date_format:Y-m-d H:i:s',
            'end_at' => 'required|date_format:Y-m-d H:i:s|after:start_at',
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'nullable|integer|min:0|max:10',
            'related_diary_key' => 'nullable|string|max:50',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $patient = Patient::findOrFail($request->patient_id);
        
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }
        
        // Check if assigned user is valid (belongs to organization or is assigned to patient)
        if ($request->assigned_to) {
            if (!$this->canAssignUser($user, $patient, $request->assigned_to)) {
                return response()->json([
                    'message' => 'Невозможно назначить задачу этому пользователю.',
                ], 422);
            }
        }
        
        $task = Task::create([
            'patient_id' => $request->patient_id,
            'title' => $request->title,
            'start_at' => Carbon::parse($request->start_at),
            'end_at' => Carbon::parse($request->end_at),
            'assigned_to' => $request->assigned_to,
            'priority' => $request->priority ?? 0,
            'related_diary_key' => $request->related_diary_key,
            'status' => Task::STATUS_PENDING,
        ]);
        
        $task->load(['patient', 'assignedTo']);
        
        return response()->json($task, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/route-sheet/{id}",
     *     tags={"Route Sheet"},
     *     summary="Update a task",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="start_at", type="string", format="date-time"),
     *             @OA\Property(property="end_at", type="string", format="date-time"),
     *             @OA\Property(property="assigned_to", type="integer", nullable=true),
     *             @OA\Property(property="priority", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Task updated successfully"),
     *     @OA\Response(response=403, description="Access denied")
     * )
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $patient = $task->patient;
        
        // Only clients, managers, doctors, admins, owners can update tasks
        if (!$user->hasAnyRole(['client', 'manager', 'doctor', 'admin', 'owner'])) {
            return response()->json([
                'message' => 'У вас нет прав на обновление задач.',
            ], 403);
        }
        
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этой задаче.',
            ], 403);
        }
        
        // Only pending tasks can be updated
        if ($task->status !== Task::STATUS_PENDING) {
            return response()->json([
                'message' => 'Обновлять можно только задачи со статусом "ожидает".',
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'start_at' => 'sometimes|date_format:Y-m-d H:i:s',
            'end_at' => 'sometimes|date_format:Y-m-d H:i:s',
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'sometimes|integer|min:0|max:10',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $data = $validator->validated();
        
        if (isset($data['start_at'])) {
            $data['start_at'] = Carbon::parse($data['start_at']);
        }
        if (isset($data['end_at'])) {
            $data['end_at'] = Carbon::parse($data['end_at']);
        }
        
        $task->update($data);
        $task->load(['patient', 'assignedTo']);
        
        return response()->json($task);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/route-sheet/{id}/reschedule",
     *     tags={"Route Sheet"},
     *     summary="Reschedule a task",
     *     description="Move a task to a different time. Original time is preserved for history.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"start_at", "end_at", "reason"},
     *             @OA\Property(property="start_at", type="string", format="date-time", example="2024-01-01 14:00:00"),
     *             @OA\Property(property="end_at", type="string", format="date-time", example="2024-01-01 14:30:00"),
     *             @OA\Property(property="reason", type="string", example="Patient was sleeping")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Task rescheduled successfully")
     * )
     */
    public function reschedule(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $patient = $task->patient;
        
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет прав на перенос этой задачи.',
            ], 403);
        }
        
        // Only pending tasks can be rescheduled
        if ($task->status !== Task::STATUS_PENDING) {
            return response()->json([
                'message' => 'Переносить можно только задачи со статусом "ожидает".',
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'start_at' => 'required|date_format:Y-m-d H:i:s',
            'end_at' => 'required|date_format:Y-m-d H:i:s|after:start_at',
            'reason' => 'required|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        // Save original time if not already rescheduled
        if (!$task->isRescheduled()) {
            $task->original_start_at = $task->start_at;
            $task->original_end_at = $task->end_at;
        }
        
        $task->start_at = Carbon::parse($request->start_at);
        $task->end_at = Carbon::parse($request->end_at);
        $task->reschedule_reason = $request->reason;
        $task->rescheduled_by = $user->id;
        $task->rescheduled_at = now();
        $task->save();
        
        $task->load(['patient', 'assignedTo', 'rescheduledBy']);
        
        return response()->json(array_merge($task->toArray(), [
            'is_rescheduled' => true,
            'message' => 'Задача успешно перенесена',
        ]));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/route-sheet/{id}/complete",
     *     tags={"Route Sheet"},
     *     summary="Mark task as completed",
     *     description="Complete a task with optional comment, photos, and measurement value",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="comment", type="string", nullable=true),
     *             @OA\Property(property="photos", type="array", @OA\Items(type="string", format="binary"), description="Array of photo files"),
     *             @OA\Property(property="value", type="object", nullable=true, description="Measurement value for diary entry"),
     *             @OA\Property(property="completed_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Task completed successfully")
     * )
     */
    public function complete(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $patient = $task->patient;
        
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет прав на выполнение этой задачи.',
            ], 403);
        }
        
        if ($task->status !== Task::STATUS_PENDING) {
            return response()->json([
                'message' => 'Выполнять можно только задачи со статусом "ожидает".',
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'comment' => 'nullable|string|max:1000',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|max:5120', // 5MB max per photo
            'value' => 'nullable|array',
            'completed_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        // Handle photo uploads
        $photoUrls = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('task-photos/' . $task->id, 'public');
                $photoUrls[] = Storage::url($path);
            }
        }
        
        $completedAt = $request->completed_at 
            ? Carbon::parse($request->completed_at) 
            : now();
        
        $task->update([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => $completedAt,
            'completed_by' => $user->id,
            'comment' => $request->comment,
            'photos' => !empty($photoUrls) ? $photoUrls : $task->photos,
        ]);
        
        // Create diary entry if task has related_diary_key and value is provided
        if ($task->related_diary_key && $request->value) {
            $this->createDiaryEntryFromTask($task, $user, $request->value);
        }
        
        // Send notifications
        $this->sendTaskNotifications($task, $user);
        
        $task->load(['patient', 'assignedTo', 'completedBy']);
        
        return response()->json(array_merge($task->toArray(), [
            'message' => 'Задача успешно выполнена',
        ]));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/route-sheet/{id}/miss",
     *     tags={"Route Sheet"},
     *     summary="Mark task as missed (not completed)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Patient refused")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Task marked as missed")
     * )
     */
    public function miss(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $patient = $task->patient;
        
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет прав на обновление этой задачи.',
            ], 403);
        }
        
        if ($task->status !== Task::STATUS_PENDING) {
            return response()->json([
                'message' => 'Отмечать как пропущенные можно только задачи со статусом "ожидает".',
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $task->update([
            'status' => Task::STATUS_MISSED,
            'completed_at' => now(),
            'completed_by' => $user->id,
            'comment' => $request->reason,
        ]);
        
        // Send notifications (including critical notification to manager)
        $this->sendTaskNotifications($task, $user);
        
        $task->load(['patient', 'assignedTo', 'completedBy']);
        
        return response()->json(array_merge($task->toArray(), [
            'message' => 'Задача отмечена как пропущенная',
        ]));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/route-sheet/{id}",
     *     tags={"Route Sheet"},
     *     summary="Delete a task",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Task deleted successfully"),
     *     @OA\Response(response=403, description="Access denied")
     * )
     */
    public function destroy(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $patient = $task->patient;
        
        // Only clients, managers, admins, owners can delete tasks
        if (!$user->hasAnyRole(['client', 'manager', 'admin', 'owner'])) {
            return response()->json([
                'message' => 'У вас нет прав на удаление задач.',
            ], 403);
        }
        
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет прав на удаление этой задачи.',
            ], 403);
        }
        
        // Only pending or cancelled tasks can be deleted
        if (!in_array($task->status, [Task::STATUS_PENDING, Task::STATUS_CANCELLED])) {
            return response()->json([
                'message' => 'Удалять можно только задачи со статусом "ожидает" или "отменена".',
            ], 422);
        }
        
        $task->delete();
        
        return response()->json([
            'message' => 'Задача успешно удалена',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/route-sheet/my-tasks",
     *     tags={"Route Sheet"},
     *     summary="Get current user's assigned tasks (for caregivers)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Tasks retrieved successfully")
     * )
     */
    public function myTasks(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->query('date', now()->format('Y-m-d'));
        
        $query = Task::with(['patient', 'template'])
            ->whereDate('start_at', $date)
            ->orderBy('start_at', 'asc')
            ->orderBy('priority', 'desc');
        
        // For caregivers: tasks assigned to them
        if ($user->hasRole('caregiver')) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere(function ($q2) use ($user) {
                      // Also include unassigned tasks for patients they're assigned to
                      $q2->whereNull('assigned_to')
                         ->whereIn('patient_id', $user->assignedPatients()->pluck('patients.id'));
                  });
            });
        }
        // For doctors: similar logic
        elseif ($user->hasRole('doctor')) {
            $assignedPatientIds = $user->assignedPatients()->pluck('patients.id');
            $query->whereIn('patient_id', $assignedPatientIds);
        }
        // For managers: organization patients
        elseif ($user->hasRole('manager')) {
            $organization = $user->organization;
            if ($organization) {
                $query->whereHas('patient', function ($q) use ($organization) {
                    $q->where('organization_id', $organization->id);
                });
            }
        }
        // For clients: their patients
        elseif ($user->hasRole('client')) {
            $query->whereHas('patient', function ($q) use ($user) {
                $q->where('creator_id', $user->id);
            });
        }
        
        $tasks = $query->get()->map(function ($task) {
            return array_merge($task->toArray(), [
                'is_rescheduled' => $task->isRescheduled(),
                'is_overdue' => $task->isOverdue(),
            ]);
        });
        
        // Group by time slots for calendar view
        $timeSlots = [];
        foreach ($tasks as $task) {
            $hour = Carbon::parse($task['start_at'])->format('H:00');
            if (!isset($timeSlots[$hour])) {
                $timeSlots[$hour] = [];
            }
            $timeSlots[$hour][] = $task;
        }
        
        return response()->json([
            'date' => $date,
            'tasks' => $tasks,
            'time_slots' => $timeSlots,
            'summary' => [
                'total' => $tasks->count(),
                'pending' => $tasks->where('status', 'pending')->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
                'overdue' => $tasks->where('is_overdue', true)->count(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/route-sheet/available-employees",
     *     tags={"Route Sheet"},
     *     summary="Get available employees for task assignment",
     *     description="Returns employees with their availability status for a specific time slot",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="patient_id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="start_at", in="query", required=true, @OA\Schema(type="string", format="date-time")),
     *     @OA\Parameter(name="end_at", in="query", required=true, @OA\Schema(type="string", format="date-time")),
     *     @OA\Response(response=200, description="Available employees retrieved")
     * )
     */
    public function availableEmployees(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'start_at' => 'required|date_format:Y-m-d H:i:s',
            'end_at' => 'required|date_format:Y-m-d H:i:s',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $patient = Patient::findOrFail($request->patient_id);
        
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }
        
        $startAt = Carbon::parse($request->start_at);
        $endAt = Carbon::parse($request->end_at);
        
        // Get employees from organization or assigned to patient
        $employees = collect();
        
        if ($patient->organization) {
            // Get organization employees (caregivers and doctors)
            $employees = $patient->organization->employees()
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['caregiver', 'doctor']);
                })
                ->get();
        } else {
            // Get caregivers assigned to this patient
            $employees = $patient->assignedUsers()
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['caregiver', 'doctor']);
                })
                ->get();
        }
        
        // Check availability for each employee
        $employeesWithAvailability = $employees->map(function ($employee) use ($startAt, $endAt) {
            // Count tasks in the time range
            $conflictingTasks = Task::where('assigned_to', $employee->id)
                ->where('status', Task::STATUS_PENDING)
                ->where(function ($q) use ($startAt, $endAt) {
                    $q->whereBetween('start_at', [$startAt, $endAt])
                      ->orWhereBetween('end_at', [$startAt, $endAt])
                      ->orWhere(function ($q2) use ($startAt, $endAt) {
                          $q2->where('start_at', '<=', $startAt)
                             ->where('end_at', '>=', $endAt);
                      });
                })
                ->count();
            
            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'role' => $employee->roles->first()?->name,
                'is_available' => $conflictingTasks === 0,
                'conflicting_tasks_count' => $conflictingTasks,
            ];
        });
        
        return response()->json([
            'employees' => $employeesWithAvailability,
            'time_slot' => [
                'start_at' => $startAt->toDateTimeString(),
                'end_at' => $endAt->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Create a diary entry from a completed task with measurement value.
     */
    private function createDiaryEntryFromTask(Task $task, $user, array $value): void
    {
        $patient = $task->patient;
        
        // Get or create diary for patient
        $diary = $patient->diary;
        if (!$diary) {
            $diary = \App\Models\Diary::create([
                'patient_id' => $patient->id,
            ]);
        }
        
        // Determine diary type based on key
        $physicalKeys = ['temperature', 'blood_pressure', 'pulse', 'weight', 'height', 'blood_sugar', 'saturation', 'breathing_rate', 'pain_level'];
        $diaryType = in_array($task->related_diary_key, $physicalKeys) ? 'physical' : 'care';

        DiaryEntry::create([
            'diary_id' => $diary->id,
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
        if ($task->status === Task::STATUS_MISSED && $patient->organization) {
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
        // Клиент может видеть только своих пациентов (где owner_id = user.id)
        if ($user->isClient()) {
            return $patient->owner_id === $user->id;
        }

        // Частная сиделка может видеть только назначенных пациентов
        if ($user->isPrivateCaregiver()) {
            return $patient->assignedUsers()->where('user_id', $user->id)->exists();
        }

        // Сотрудник организации
        if ($user->organization_id) {
            $organization = $user->organization;
            
            if (!$organization) {
                return false;
            }

            // Пациент должен принадлежать той же организации
            if ($patient->organization_id !== $organization->id) {
                return false;
            }

            // Владельцы и админы организации имеют доступ ко всем пациентам организации
            if ($user->hasAnyRole(['owner', 'admin'])) {
                return true;
            }

            // Пансионат: все сотрудники видят всех пациентов организации
            if ($organization->isBoardingHouse()) {
                return true;
            }

            // Агентство: только назначенные пациенты
            if ($organization->isAgency()) {
                return $patient->assignedUsers()->where('user_id', $user->id)->exists();
            }
        }

        return false;
    }

    /**
     * Check if a user can be assigned to a task for this patient.
     */
    private function canAssignUser($currentUser, Patient $patient, int $assigneeId): bool
    {
        $assignee = \App\Models\User::find($assigneeId);
        if (!$assignee) {
            return false;
        }
        
        // Assignee must be a caregiver or doctor
        if (!$assignee->hasAnyRole(['caregiver', 'doctor'])) {
            return false;
        }
        
        // Check if assignee belongs to the same organization
        if ($patient->organization) {
            if ($assignee->organization_id === $patient->organization_id) {
                return true;
            }
        }
        
        // Check if assignee is assigned to this patient
        if ($patient->assignedUsers()->where('user_id', $assigneeId)->exists()) {
            return true;
        }
        
        return false;
    }
}
