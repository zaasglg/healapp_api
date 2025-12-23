<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskTemplateRequest;
use App\Models\Patient;
use App\Models\TaskTemplate;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Task Templates",
 *     description="API endpoints for managing task templates (care plans)"
 * )
 */
class TaskTemplateController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/task-templates",
     *     tags={"Task Templates"},
     *     summary="Get task templates for a patient",
     *     description="Retrieve task templates for a specific patient. Only clients and managers can access.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="patient_id",
     *         in="query",
     *         required=true,
     *         description="Patient ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task templates retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="patient_id", type="integer", example=1),
     *                 @OA\Property(property="creator_id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Give medicine"),
     *                 @OA\Property(property="days_of_week", type="array", nullable=true, @OA\Items(type="integer"), example={1, 3, 5}),
     *                 @OA\Property(property="time_ranges", type="array", @OA\Items(type="object"), example={{"start": "09:00", "end": "09:30"}}),
     *                 @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
     *                 @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2024-12-31"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
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

        // Only clients and managers can access templates
        if (!$user->hasAnyRole(['client', 'manager', 'admin'])) {
            return response()->json([
                'message' => 'У вас нет прав на доступ к шаблонам задач.',
            ], 403);
        }

        $patientId = $request->query('patient_id');

        if (!$patientId) {
            return response()->json([
                'message' => 'Параметр patient_id обязателен',
            ], 400);
        }

        $patient = Patient::findOrFail($patientId);

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }

        $templates = TaskTemplate::where('patient_id', $patientId)->get();

        return response()->json($templates, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/task-templates",
     *     tags={"Task Templates"},
     *     summary="Create a new task template",
     *     description="Create a new task template and generate initial tasks. Only clients and managers can create templates.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"patient_id", "title", "time_ranges", "start_date"},
     *             @OA\Property(property="patient_id", type="integer", example=1, description="Patient ID"),
     *             @OA\Property(property="title", type="string", example="Give medicine", description="Task name"),
     *             @OA\Property(property="days_of_week", type="array", nullable=true, @OA\Items(type="integer"), example={1, 3, 5}, description="Array of day numbers (0=Sunday, 6=Saturday). Null = every day"),
     *             @OA\Property(property="time_ranges", type="array", @OA\Items(
     *                 @OA\Property(property="start", type="string", example="09:00", description="Start time in H:i format"),
     *                 @OA\Property(property="end", type="string", example="09:30", description="End time in H:i format")
     *             ), example={{"start": "09:00", "end": "09:30"}, {"start": "20:00", "end": "20:30"}}),
     *             @OA\Property(property="start_date", type="string", format="date", example="2024-01-01", description="When this plan starts"),
     *             @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2024-12-31", description="If null, runs indefinitely"),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Whether the template is active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Task template created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="patient_id", type="integer", example=1),
     *             @OA\Property(property="creator_id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Give medicine"),
     *             @OA\Property(property="days_of_week", type="array", nullable=true, @OA\Items(type="integer"), example={1, 3, 5}),
     *             @OA\Property(property="time_ranges", type="array", @OA\Items(type="object"), example={{"start": "09:00", "end": "09:30"}}),
     *             @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
     *             @OA\Property(property="end_date", type="string", format="date", nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true),
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
     *             @OA\Property(property="message", type="string", example="You do not have permission to create task templates.")
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
    public function store(StoreTaskTemplateRequest $request): JsonResponse
    {
        $user = $request->user();

        // Only clients and managers can create templates
        if (!$user->hasAnyRole(['client', 'manager', 'admin'])) {
            return response()->json([
                'message' => 'У вас нет прав на создание шаблонов задач.',
            ], 403);
        }

        $patient = Patient::findOrFail($request->patient_id);

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }

        $data = $request->validated();
        $data['creator_id'] = $user->id;

        $template = TaskTemplate::create($data);

        // Generate tasks for the first week
        $taskService = new TaskService();
        $taskService->generateForPatient($patient, 7);

        return response()->json($template, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/task-templates/{id}",
     *     tags={"Task Templates"},
     *     summary="Get a specific task template",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Template retrieved successfully"),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Template not found")
     * )
     */
    public function show(Request $request, TaskTemplate $taskTemplate): JsonResponse
    {
        $user = $request->user();
        $patient = $taskTemplate->patient;

        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому шаблону.',
            ], 403);
        }

        $taskTemplate->load(['patient', 'creator', 'assignedTo']);

        return response()->json($taskTemplate);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/task-templates/{id}",
     *     tags={"Task Templates"},
     *     summary="Update a task template",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="assigned_to", type="integer", nullable=true),
     *             @OA\Property(property="days_of_week", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="time_ranges", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date", nullable=true),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="related_diary_key", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Template updated successfully"),
     *     @OA\Response(response=403, description="Access denied")
     * )
     */
    public function update(Request $request, TaskTemplate $taskTemplate): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['client', 'manager', 'admin'])) {
            return response()->json([
                'message' => 'У вас нет прав на обновление шаблонов задач.',
            ], 403);
        }

        $patient = $taskTemplate->patient;

        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'title' => ['sometimes', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'days_of_week' => ['sometimes', 'nullable', 'array'],
            'days_of_week.*' => ['integer', 'min:0', 'max:6'],
            'time_ranges' => ['sometimes', 'array', 'min:1'],
            'time_ranges.*.start' => ['required_with:time_ranges', 'string', 'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'],
            'time_ranges.*.end' => ['required_with:time_ranges', 'string', 'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'],
            'time_ranges.*.assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'time_ranges.*.priority' => ['nullable', 'integer', 'min:0', 'max:10'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'related_diary_key' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $taskTemplate->update($validator->validated());

        // Regenerate future tasks if time_ranges or days_of_week changed
        if ($request->has('time_ranges') || $request->has('days_of_week')) {
            // Delete future pending tasks
            $taskTemplate->tasks()
                ->where('status', 'pending')
                ->where('start_at', '>', now())
                ->delete();

            // Regenerate tasks
            $taskService = new TaskService();
            $taskService->generateForPatient($patient, 7);
        }

        $taskTemplate->load(['patient', 'creator', 'assignedTo']);

        return response()->json($taskTemplate);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/task-templates/{id}/toggle",
     *     tags={"Task Templates"},
     *     summary="Toggle template active status",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Template status toggled")
     * )
     */
    public function toggle(Request $request, TaskTemplate $taskTemplate): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['client', 'manager', 'admin'])) {
            return response()->json([
                'message' => 'У вас нет прав на обновление шаблонов задач.',
            ], 403);
        }

        $patient = $taskTemplate->patient;

        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }

        $taskTemplate->is_active = !$taskTemplate->is_active;
        $taskTemplate->save();

        // If deactivated, optionally delete future pending tasks
        if (!$taskTemplate->is_active) {
            $taskTemplate->tasks()
                ->where('status', 'pending')
                ->where('start_at', '>', now())
                ->delete();
        } else {
            // If activated, generate new tasks
            $taskService = new TaskService();
            $taskService->generateForPatient($patient, 7);
        }

        return response()->json([
            'message' => $taskTemplate->is_active ? 'Шаблон активирован' : 'Шаблон деактивирован',
            'is_active' => $taskTemplate->is_active,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/task-templates/{id}",
     *     tags={"Task Templates"},
     *     summary="Delete a task template",
     *     description="Delete a task template and all future pending tasks linked to it. Only clients and managers can delete templates.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Task Template ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task template deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Task template deleted successfully")
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
     *             @OA\Property(property="message", type="string", example="You do not have permission to delete this template.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Template not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\TaskTemplate] {id}")
     *         )
     *     )
     * )
     */
    public function destroy(Request $request, TaskTemplate $taskTemplate): JsonResponse
    {
        $user = $request->user();

        // Only clients and managers can delete templates
        if (!$user->hasAnyRole(['client', 'manager', 'admin'])) {
            return response()->json([
                'message' => 'У вас нет прав на удаление шаблонов задач.',
            ], 403);
        }

        $patient = $taskTemplate->patient;

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }

        // Delete future pending tasks
        $taskTemplate->tasks()
            ->where('status', 'pending')
            ->where('start_at', '>', now())
            ->delete();

        $taskTemplate->delete();

        return response()->json([
            'message' => 'Шаблон задачи успешно удалён',
        ], 200);
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

        return false;
    }
}
