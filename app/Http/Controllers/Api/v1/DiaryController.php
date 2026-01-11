<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiaryEntryRequest;
use App\Models\Diary;
use App\Models\DiaryEntry;
use App\Models\Organization;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Diary",
 *     description="API endpoints for patient diary management"
 * )
 */
class DiaryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/diary",
     *     tags={"Diary"},
     *     summary="Get all diaries created by the authenticated user",
     *     description="Retrieve all diaries for patients created by the authenticated user. Access is restricted to user's own patients.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         description="Filter entries from this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         description="Filter entries to this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Diaries retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="patient_id", type="integer", example=1),
     *                 @OA\Property(property="patient", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="first_name", type="string", example="Иван"),
     *                     @OA\Property(property="last_name", type="string", example="Иванов"),
     *                     @OA\Property(property="middle_name", type="string", example="Иванович"),
     *                     @OA\Property(property="full_name", type="string", example="Иванов Иван Иванович"),
     *                     @OA\Property(property="birth_date", type="string", format="date", example="1980-01-01"),
     *                     @OA\Property(property="gender", type="string", example="male"),
     *                     @OA\Property(property="weight", type="number", example=75.5),
     *                     @OA\Property(property="height", type="number", example=175.0),
     *                     @OA\Property(property="mobility", type="string", example="independent"),
     *                     @OA\Property(property="diagnoses", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="needed_services", type="array", @OA\Items(type="string"))
     *                 ),
     *                 @OA\Property(property="pinned_parameters", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="settings", type="object", nullable=true),
     *                 @OA\Property(property="entries", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="diary_id", type="integer", example=1),
     *                         @OA\Property(property="author_id", type="integer", example=1),
     *                         @OA\Property(property="type", type="string", example="physical", enum={"care", "physical", "excretion", "symptom"}),
     *                         @OA\Property(property="key", type="string", example="temperature"),
     *                         @OA\Property(property="value", type="object", description="Entry value as JSON object"),
     *                         @OA\Property(property="notes", type="string", nullable=true, example="Normal temperature"),
     *                         @OA\Property(property="recorded_at", type="string", format="date-time", example="2024-01-01T10:00:00.000000Z"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Используем метод accessibleDiaries из модели User для правильной фильтрации
        $diariesQuery = $user->accessibleDiaries();

        // Загружаем связанные данные с фильтрацией записей по дате
        $diaries = $diariesQuery
            ->with(['patient:id,first_name,last_name,middle_name,birth_date,gender,weight,height,mobility,diagnoses,needed_services,organization_id', 'entries' => function ($query) use ($request) {
                // Filter entries by date range if provided
                if ($request->has('from_date')) {
                    $query->whereDate('recorded_at', '>=', $request->query('from_date'));
                }
                if ($request->has('to_date')) {
                    $query->whereDate('recorded_at', '<=', $request->query('to_date'));
                }
                $query->orderBy('recorded_at', 'desc');
            }])
            ->get();

        // Format the response
        $formattedDiaries = $diaries->map(function ($diary) {
            return [
                'id' => $diary->id,
                'patient_id' => $diary->patient_id,
                'patient' => [
                    'id' => $diary->patient->id,
                    'first_name' => $diary->patient->first_name,
                    'last_name' => $diary->patient->last_name,
                    'middle_name' => $diary->patient->middle_name,
                    'full_name' => trim($diary->patient->first_name . ' ' . ($diary->patient->middle_name ?? '') . ' ' . $diary->patient->last_name),
                    'birth_date' => $diary->patient->birth_date,
                    'gender' => $diary->patient->gender,
                    'weight' => $diary->patient->weight,
                    'height' => $diary->patient->height,
                    'mobility' => $diary->patient->mobility,
                    'diagnoses' => $diary->patient->diagnoses ?? [],
                    'needed_services' => $diary->patient->needed_services ?? [],
                ],
                'pinned_parameters' => $diary->pinned_parameters ?? [],
                'settings' => $diary->settings,
                'entries' => $diary->entries,
                'created_at' => $diary->created_at,
                'updated_at' => $diary->updated_at,
            ];
        });

        return response()->json($formattedDiaries, 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/diary/{id}",
     *     tags={"Diary"},
     *     summary="Get a single diary by ID",
     *     description="Retrieve a specific diary with patient info and entries. Access is restricted based on user permissions.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Diary ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         description="Filter entries from this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         description="Filter entries to this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Diary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="patient_id", type="integer", example=1),
     *             @OA\Property(property="patient", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="Иван"),
     *                 @OA\Property(property="last_name", type="string", example="Иванов"),
     *                 @OA\Property(property="middle_name", type="string", example="Иванович"),
     *                 @OA\Property(property="full_name", type="string", example="Иванов Иван Иванович"),
     *                 @OA\Property(property="birth_date", type="string", format="date", example="1980-01-01"),
     *                 @OA\Property(property="gender", type="string", example="male"),
     *                 @OA\Property(property="weight", type="number", example=75.5),
     *                 @OA\Property(property="height", type="number", example=175.0),
     *                 @OA\Property(property="mobility", type="string", example="independent"),
     *                 @OA\Property(property="diagnoses", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="needed_services", type="array", @OA\Items(type="string"))
     *             ),
     *             @OA\Property(property="pinned_parameters", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="settings", type="object", nullable=true),
     *             @OA\Property(property="entries", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="diary_id", type="integer", example=1),
     *                     @OA\Property(property="author_id", type="integer", example=1),
     *                     @OA\Property(property="source_task_id", type="integer", nullable=true, example=5, description="ID задачи из маршрутного листа"),
     *                     @OA\Property(property="type", type="string", example="physical"),
     *                     @OA\Property(property="key", type="string", example="temperature", description="Ключ: temperature, blood_pressure, pulse, meal, medication, walk, hygiene, diaper_change и др."),
     *                     @OA\Property(property="value", type="object"),
     *                     @OA\Property(property="notes", type="string", nullable=true),
     *                     @OA\Property(property="recorded_at", type="string", format="date-time"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
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
     *             @OA\Property(property="message", type="string", example="You do not have access to this diary.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Diary not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Diary not found.")
     *         )
     *     )
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $diary = Diary::with(['patient'])->find($id);
        
        if (!$diary) {
            return response()->json([
                'message' => 'Дневник не найден.',
            ], 404);
        }

        // Check access
        if (!$this->canAccessPatient($user, $diary->patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому дневнику.',
            ], 403);
        }

        // Load entries with optional date filtering
        $entriesQuery = $diary->entries();
        
        if ($request->has('from_date')) {
            $entriesQuery->whereDate('recorded_at', '>=', $request->query('from_date'));
        }
        if ($request->has('to_date')) {
            $entriesQuery->whereDate('recorded_at', '<=', $request->query('to_date'));
        }
        
        $entries = $entriesQuery->orderBy('recorded_at', 'desc')->get();

        return response()->json([
            'id' => $diary->id,
            'patient_id' => $diary->patient_id,
            'patient' => [
                'id' => $diary->patient->id,
                'first_name' => $diary->patient->first_name,
                'last_name' => $diary->patient->last_name,
                'middle_name' => $diary->patient->middle_name,
                'full_name' => trim($diary->patient->first_name . ' ' . ($diary->patient->middle_name ?? '') . ' ' . $diary->patient->last_name),
                'birth_date' => $diary->patient->birth_date,
                'gender' => $diary->patient->gender,
                'weight' => $diary->patient->weight,
                'height' => $diary->patient->height,
                'mobility' => $diary->patient->mobility,
                'diagnoses' => $diary->patient->diagnoses ?? [],
                'needed_services' => $diary->patient->needed_services ?? [],
            ],
            'pinned_parameters' => $diary->pinned_parameters ?? [],
            'settings' => $diary->settings,
            'entries' => $entries,
            'created_at' => $diary->created_at,
            'updated_at' => $diary->updated_at,
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/diary/create",
     *     tags={"Diary"},
     *     summary="Create a new diary for a patient",
     *     description="Explicitly create a new diary for a patient with optional pinned parameters. Returns error if diary already exists.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"patient_id"},
     *             @OA\Property(property="patient_id", type="integer", example=1, description="Patient ID"),
     *             @OA\Property(property="pinned_parameters", type="array", description="Optional pinned parameters with timers",
     *                 @OA\Items(
     *                     @OA\Property(property="key", type="string", example="blood_pressure"),
     *                     @OA\Property(property="interval_minutes", type="integer", example=60)
     *                 )
     *             ),
     *             @OA\Property(property="settings", type="object", nullable=true, description="Optional diary settings")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Diary created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="patient_id", type="integer", example=1),
     *             @OA\Property(property="pinned_parameters", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="settings", type="object", nullable=true),
     *             @OA\Property(property="entries", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - patient_id is required",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="patient_id is required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You do not have access to this patient.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict - Diary already exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Diary already exists for this patient"),
     *             @OA\Property(property="diary_id", type="integer", example=1)
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
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'pinned_parameters' => 'nullable|array',
            'pinned_parameters.*.key' => 'required_with:pinned_parameters|string',
            'pinned_parameters.*.interval_minutes' => 'nullable|integer|min:1',
            'pinned_parameters.*.times' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        $patient = Patient::findOrFail($request->patient_id);
        $user = $request->user();

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }

        // Check if diary already exists
        if ($patient->diary) {
            return response()->json([
                'message' => 'Дневник для этого пациента уже существует',
                'diary_id' => $patient->diary->id,
            ], 409);
        }

        // Create diary
        $diary = Diary::create([
            'patient_id' => $patient->id,
            'pinned_parameters' => $request->pinned_parameters,
            'settings' => $request->settings,
        ]);

        // Grant full access to the creator (important for private caregivers and agency employees)
        $diary->grantAccess($user, 'full');

        return response()->json([
            'id' => $diary->id,
            'patient_id' => $diary->patient_id,
            'pinned_parameters' => $diary->pinned_parameters ?? [],
            'settings' => $diary->settings,
            'entries' => [],
            'created_at' => $diary->created_at,
            'updated_at' => $diary->updated_at,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/diary",
     *     tags={"Diary"},
     *     summary="Create diary entry or create diary for patient",
     *     description="Create a new diary entry for a patient. If diary doesn't exist, it will be created. Access is restricted to users who have access to the patient.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"patient_id", "type", "key", "value", "recorded_at"},
     *             @OA\Property(property="patient_id", type="integer", example=1, description="Patient ID"),
     *             @OA\Property(property="type", type="string", example="physical", description="Entry type", enum={"care", "physical", "excretion", "symptom"}),
     *             @OA\Property(property="key", type="string", example="temperature", description="Entry key (e.g., 'temperature', 'blood_pressure', 'mood', 'diaper_change')"),
     *             @OA\Property(property="value", type="object", description="Entry value as JSON object. Examples: value 36.6 for temperature, systolic 120 diastolic 80 for blood pressure"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Normal temperature reading", description="Optional notes"),
     *             @OA\Property(property="recorded_at", type="string", format="date-time", example="2024-01-01T10:00:00Z", description="When the event actually happened")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Diary entry created successfully. Если есть pending задача с таким же related_diary_key на сегодня, она автоматически будет отмечена как выполненная.",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="diary_id", type="integer", example=1),
     *             @OA\Property(property="author_id", type="integer", example=1),
     *             @OA\Property(property="source_task_id", type="integer", nullable=true, example=null, description="ID связанной задачи (устанавливается автоматически при синхронизации)"),
     *             @OA\Property(property="type", type="string", example="physical"),
     *             @OA\Property(property="key", type="string", example="temperature", description="Допустимые ключи: temperature, blood_pressure, pulse, blood_sugar, saturation, breathing_rate, pain_level, weight, height, hygiene, diaper_change, meal, medication, walk"),
     *             @OA\Property(property="value", type="object", description="Entry value as JSON object"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Normal temperature"),
     *             @OA\Property(property="recorded_at", type="string", format="date-time", example="2024-01-01T10:00:00.000000Z"),
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
     *             @OA\Property(property="message", type="string", example="You do not have access to this patient.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Patient not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Patient] {id}")
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
    public function store(StoreDiaryEntryRequest $request): JsonResponse
    {
        $user = $request->user();
        $patient = Patient::findOrFail($request->patient_id);

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }

        // Get or create diary for patient
        $diary = $patient->diary;
        if (!$diary) {
            $diary = Diary::create([
                'patient_id' => $patient->id,
            ]);
            // Grant full access to the creator
            $diary->grantAccess($user, 'full');
        }

        $data = $request->validated();
        
        // Remove patient_id from data, we'll use diary_id instead
        unset($data['patient_id']);
        $data['diary_id'] = $diary->id;
        
        // Автоопределение типа по ключу, если не указан
        if (empty($data['type'])) {
            $data['type'] = $this->getTypeByKey($data['key'] ?? '');
        }
        
        // Ensure value is properly formatted as array/JSON
        if (!is_array($data['value'])) {
            // If value is a string, try to decode it as JSON
            $decoded = json_decode($data['value'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['value'] = $decoded;
            } else {
                // If it's not valid JSON, wrap it in an object
                $data['value'] = ['value' => $data['value']];
            }
        }

        // Set author_id to authenticated user
        $data['author_id'] = $user->id;

        \Log::info('DiaryEntry store data before create:', $data);

        $entry = DiaryEntry::create($data);

        \Log::info('DiaryEntry created:', $entry->toArray());

        return response()->json($entry, 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/diary/pinned",
     *     tags={"Diary"},
     *     summary="Update pinned parameters for diary",
     *     description="Update the pinned parameters with timers for a patient's diary. Also allows updating diary settings.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"patient_id", "pinned_parameters"},
     *             @OA\Property(property="patient_id", type="integer", example=1),
     *             @OA\Property(property="pinned_parameters", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="key", type="string", example="blood_pressure"),
     *                     @OA\Property(property="interval_minutes", type="integer", example=60),
     *                     @OA\Property(property="last_recorded_at", type="string", format="date-time", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="settings", type="object", nullable=true,
     *                 @OA\Property(property="all_indicators", type="array", @OA\Items(type="string"), example={"vitamins", "meal", "skin_moisturizing", "walk"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pinned parameters updated successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied"
     *     )
     * )
     */
    public function updatePinned(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'pinned_parameters' => 'nullable|array', // Сделал nullable - можно обновлять только settings
            'pinned_parameters.*.key' => 'required_with:pinned_parameters|string',
            'pinned_parameters.*.label' => 'nullable|string',
            'pinned_parameters.*.interval_minutes' => 'nullable|integer|min:1',
            'pinned_parameters.*.times' => 'nullable|array',
            'pinned_parameters.*.settings' => 'nullable|array',
            'settings' => 'nullable|array',
            'settings.all_indicators' => 'nullable|array',
            'settings.all_indicators.*' => 'nullable|string',
        ]);

        $patient = Patient::findOrFail($request->patient_id);
        $user = $request->user();

        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }

        $diary = $patient->diary;
        if (!$diary) {
            $diary = Diary::create(['patient_id' => $patient->id]);
            // Grant full access to the creator
            $diary->grantAccess($user, 'full');
        }

        // Сохраняем старые параметры для сравнения
        $oldPinnedParameters = $diary->pinned_parameters ?? [];

        // Подготавливаем данные для обновления
        $updateData = [];
        
        // Если переданы pinned_parameters - обновляем их
        if ($request->has('pinned_parameters') && is_array($request->pinned_parameters)) {
            $updateData['pinned_parameters'] = $request->pinned_parameters;
        }
        
        // Если переданы settings - обновляем их
        if ($request->has('settings')) {
            $updateData['settings'] = $request->settings;
        }
        
        if (!empty($updateData)) {
            $diary->update($updateData);
        }

        // Создаём задачи в маршрутном листе для параметров с временем
        if (isset($updateData['pinned_parameters'])) {
            $this->syncTasksFromPinnedParameters($patient, $updateData['pinned_parameters'], $oldPinnedParameters, $user);
        }

        return response()->json([
            'message' => 'Закреплённые параметры успешно обновлены',
            'diary' => $diary->fresh(),
        ], 200);
    }

    /**
     * Синхронизирует задачи в маршрутном листе с закрепленными параметрами
     */
    private function syncTasksFromPinnedParameters(Patient $patient, array $newParams, array $oldParams, $user): void
    {
        $today = now()->startOfDay();
        $endDate = now()->addDays(7)->endOfDay();

        foreach ($newParams as $param) {
            if (empty($param['times'])) {
                continue;
            }

            $key = $param['key'];
            $label = $param['label'] ?? $param['key'];

            // Находим или создаём шаблон задачи для этого параметра
            $template = \App\Models\TaskTemplate::firstOrCreate(
                [
                    'patient_id' => $patient->id,
                    'related_diary_key' => $key,
                ],
                [
                    'creator_id' => $user->id,
                    'title' => $label,
                    'days_of_week' => null, // каждый день
                    'time_ranges' => [],
                    'start_date' => $today->format('Y-m-d'),
                    'is_active' => true,
                ]
            );

            // Обновляем time_ranges из times
            $timeRanges = [];
            foreach ($param['times'] as $time) {
                $timeRanges[] = [
                    'start' => $time,
                    'end' => $time, // Для показателей start = end
                ];
            }

            $template->update([
                'title' => $label,
                'time_ranges' => $timeRanges,
                'is_active' => true,
            ]);

            // Удаляем будущие pending задачи для этого шаблона
            \App\Models\Task::where('template_id', $template->id)
                ->where('status', 'pending')
                ->where('start_at', '>=', now())
                ->delete();

            // Генерируем новые задачи
            $taskService = new \App\Services\TaskService();
            $taskService->generateForPatient($patient, 7);
        }

        // Деактивируем шаблоны для удалённых параметров
        $newKeys = array_column($newParams, 'key');
        $oldKeys = array_column($oldParams, 'key');
        $removedKeys = array_diff($oldKeys, $newKeys);

        foreach ($removedKeys as $removedKey) {
            \App\Models\TaskTemplate::where('patient_id', $patient->id)
                ->where('related_diary_key', $removedKey)
                ->update(['is_active' => false]);

            // Удаляем будущие pending задачи
            \App\Models\Task::whereHas('template', function ($q) use ($patient, $removedKey) {
                $q->where('patient_id', $patient->id)
                  ->where('related_diary_key', $removedKey);
            })
                ->where('status', 'pending')
                ->where('start_at', '>=', now())
                ->delete();
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/diary/entries/{id}",
     *     tags={"Diary"},
     *     summary="Update a diary entry",
     *     description="Update an existing diary entry. Only the author or users with full diary access can update entries.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Diary entry ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", example="physical", description="Entry type", enum={"care", "physical", "excretion", "symptom"}),
     *             @OA\Property(property="key", type="string", example="temperature", description="Entry key"),
     *             @OA\Property(property="value", type="object", description="Entry value as JSON object"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Updated notes"),
     *             @OA\Property(property="recorded_at", type="string", format="date-time", example="2024-01-01T10:00:00Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Diary entry updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="diary_id", type="integer", example=1),
     *             @OA\Property(property="author_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", example="physical"),
     *             @OA\Property(property="key", type="string", example="temperature"),
     *             @OA\Property(property="value", type="object"),
     *             @OA\Property(property="notes", type="string", nullable=true),
     *             @OA\Property(property="recorded_at", type="string", format="date-time"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You do not have permission to update this entry.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Entry not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Diary entry not found.")
     *         )
     *     )
     * )
     */
    public function updateEntry(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $entry = DiaryEntry::with('diary.patient')->find($id);
        
        if (!$entry) {
            return response()->json([
                'message' => 'Запись не найдена.',
            ], 404);
        }

        // Check access to the diary's patient
        if (!$this->canAccessPatient($user, $entry->diary->patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этой записи.',
            ], 403);
        }

        // Validate request
        $request->validate([
            'type' => 'sometimes|string|in:care,physical,excretion,symptom',
            'key' => 'sometimes|string|max:255',
            'value' => 'sometimes|array',
            'notes' => 'nullable|string|max:1000',
            'recorded_at' => 'sometimes|date',
        ]);

        // Update only provided fields
        $updateData = [];
        
        if ($request->has('type')) {
            $updateData['type'] = $request->type;
        }
        if ($request->has('key')) {
            $updateData['key'] = $request->key;
        }
        if ($request->has('value')) {
            $updateData['value'] = $request->value;
        }
        if ($request->has('notes')) {
            $updateData['notes'] = $request->notes;
        }
        if ($request->has('recorded_at')) {
            $updateData['recorded_at'] = $request->recorded_at;
        }

        $entry->update($updateData);

        return response()->json($entry->fresh(), 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/diary/entries/{id}",
     *     tags={"Diary"},
     *     summary="Delete a diary entry",
     *     description="Delete an existing diary entry. Only the author or users with full diary access can delete entries.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Diary entry ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Diary entry deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Diary entry deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You do not have permission to delete this entry.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Entry not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Diary entry not found.")
     *         )
     *     )
     * )
     */
    public function deleteEntry(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $entry = DiaryEntry::with('diary.patient')->find($id);
        
        if (!$entry) {
            return response()->json([
                'message' => 'Запись не найдена.',
            ], 404);
        }

        // Check access to the diary's patient
        if (!$this->canAccessPatient($user, $entry->diary->patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этой записи.',
            ], 403);
        }

        $entry->delete();

        return response()->json([
            'message' => 'Запись успешно удалена.',
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/diary/{id}/entries/sync",
     *     tags={"Diary"},
     *     summary="Sync all diary entries",
     *     description="Bulk synchronize diary entries. Pass an array of entries - new ones will be created, existing ones updated, and missing ones deleted.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Diary ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"entries"},
     *             @OA\Property(property="entries", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", nullable=true, description="Entry ID (for updates)"),
     *                     @OA\Property(property="type", type="string", example="physical"),
     *                     @OA\Property(property="key", type="string", example="temperature"),
     *                     @OA\Property(property="value", type="object"),
     *                     @OA\Property(property="notes", type="string", nullable=true),
     *                     @OA\Property(property="recorded_at", type="string", format="date-time"),
     *                     @OA\Property(property="_delete", type="boolean", nullable=true, description="Set to true to delete this entry")
     *                 )
     *             ),
     *             @OA\Property(property="delete_missing", type="boolean", default=false, description="If true, entries not in the array will be deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Entries synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="created", type="integer"),
     *             @OA\Property(property="updated", type="integer"),
     *             @OA\Property(property="deleted", type="integer"),
     *             @OA\Property(property="entries", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Diary not found")
     * )
     */
    public function syncEntries(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $diary = Diary::with('patient')->find($id);
        
        if (!$diary) {
            return response()->json([
                'message' => 'Дневник не найден.',
            ], 404);
        }

        // Check access
        if (!$this->canAccessPatient($user, $diary->patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому дневнику.',
            ], 403);
        }

        $request->validate([
            'entries' => 'required|array',
            'entries.*.id' => 'nullable|integer',
            'entries.*.type' => 'nullable|string', // Теперь опционально - автоопределяется по key
            'entries.*.key' => 'required_without:entries.*._delete|string|max:255',
            'entries.*.value' => 'required_without:entries.*._delete|array',
            'entries.*.notes' => 'nullable|string|max:1000',
            'entries.*.recorded_at' => 'required_without:entries.*._delete|date',
            'entries.*._delete' => 'nullable|boolean',
            'delete_missing' => 'nullable|boolean',
        ]);

        $entries = $request->entries;
        $deleteMissing = $request->boolean('delete_missing', false);
        
        $createdCount = 0;
        $updatedCount = 0;
        $deletedCount = 0;
        $processedIds = [];
        $resultEntries = [];

        foreach ($entries as $entryData) {
            // Если помечена на удаление
            if (!empty($entryData['_delete']) && !empty($entryData['id'])) {
                $entry = DiaryEntry::where('diary_id', $diary->id)
                    ->where('id', $entryData['id'])
                    ->first();
                    
                if ($entry) {
                    $entry->delete();
                    $deletedCount++;
                }
                continue;
            }

            // Автоопределение типа по ключу, если не указан
            $type = $entryData['type'] ?? $this->getTypeByKey($entryData['key'] ?? '');

            // Обновление существующей записи
            if (!empty($entryData['id'])) {
                $entry = DiaryEntry::where('diary_id', $diary->id)
                    ->where('id', $entryData['id'])
                    ->first();
                    
                if ($entry) {
                    $updateData = [];
                    $updateData['type'] = $type;
                    if (isset($entryData['key'])) $updateData['key'] = $entryData['key'];
                    if (isset($entryData['value'])) $updateData['value'] = $entryData['value'];
                    if (array_key_exists('notes', $entryData)) $updateData['notes'] = $entryData['notes'];
                    if (isset($entryData['recorded_at'])) $updateData['recorded_at'] = $entryData['recorded_at'];
                    
                    $entry->update($updateData);
                    $updatedCount++;
                    $processedIds[] = $entry->id;
                    $resultEntries[] = $entry->fresh();
                    continue;
                }
            }

            // Создание новой записи
            $entry = DiaryEntry::create([
                'diary_id' => $diary->id,
                'author_id' => $user->id,
                'type' => $type,
                'key' => $entryData['key'],
                'value' => $entryData['value'],
                'notes' => $entryData['notes'] ?? null,
                'recorded_at' => $entryData['recorded_at'],
            ]);
            
            $createdCount++;
            $processedIds[] = $entry->id;
            $resultEntries[] = $entry;
        }

        // Удаляем записи, которые не были в массиве (если включена опция)
        if ($deleteMissing && !empty($processedIds)) {
            $deletedCount += DiaryEntry::where('diary_id', $diary->id)
                ->whereNotIn('id', $processedIds)
                ->delete();
        }

        return response()->json([
            'message' => 'Записи успешно синхронизированы.',
            'created' => $createdCount,
            'updated' => $updatedCount,
            'deleted' => $deletedCount,
            'entries' => $resultEntries,
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/diary/{id}/parameters",
     *     tags={"Diary"},
     *     summary="Update pinned parameters by diary ID",
     *     description="Update the pinned parameters for a diary using diary ID in URL.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Diary ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="key", type="string", example="blood_pressure"),
     *                 @OA\Property(property="interval_minutes", type="integer", example=60),
     *                 @OA\Property(property="times", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Parameters updated successfully"),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Diary not found")
     * )
     */
    public function updateParameters(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $diary = Diary::with('patient')->find($id);
        
        if (!$diary) {
            return response()->json([
                'message' => 'Дневник не найден.',
            ], 404);
        }

        // Check access
        if (!$this->canAccessPatient($user, $diary->patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому дневнику.',
            ], 403);
        }

        // Тело запроса — это массив параметров напрямую
        $parameters = $request->all();
        
        // Валидация массива параметров
        $request->validate([
            '*.key' => 'required|string',
            '*.label' => 'nullable|string',
            '*.interval_minutes' => 'nullable|integer|min:1',
            '*.times' => 'nullable|array',
            '*.settings' => 'nullable|array',
        ]);

        // Сохраняем старые параметры для сравнения
        $oldPinnedParameters = $diary->pinned_parameters ?? [];

        $diary->update([
            'pinned_parameters' => $parameters,
        ]);

        // Создаём задачи в маршрутном листе для параметров с временем
        $this->syncTasksFromPinnedParameters($diary->patient, $parameters, $oldPinnedParameters, $user);

        return response()->json([
            'message' => 'Параметры успешно обновлены.',
            'diary' => $diary->fresh(),
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/diary/{id}/access",
     *     tags={"Diary"},
     *     summary="Get users with access to diary",
     *     description="Retrieve list of users who have access to a specific diary. Only owner/admin of organization or diary creator can view this.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Diary ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Access list retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="Иван"),
     *                 @OA\Property(property="last_name", type="string", example="Иванов"),
     *                 @OA\Property(property="phone", type="string", example="+79001234567"),
     *                 @OA\Property(property="permission", type="string", example="edit", enum={"view", "edit", "full"}),
     *                 @OA\Property(property="status", type="string", example="active", enum={"active", "revoked"}),
     *                 @OA\Property(property="granted_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Diary not found"
     *     )
     * )
     */
    public function getAccess(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $diary = Diary::find($id);
        
        if (!$diary) {
            return response()->json([
                'message' => 'Дневник не найден.',
            ], 404);
        }

        // Проверяем доступ: любой пользователь с доступом к дневнику может видеть список
        // Проверяем доступ: любой пользователь с доступом к дневнику может видеть список
        // А также клиент у которого есть роль admin
        $isClientAdmin = $user->isClient() && $user->hasRole('admin');

        if (!$diary->hasAccess($user) && !$isClientAdmin) {
            return response()->json([
                'message' => 'У вас нет доступа к этому дневнику.',
            ], 403);
        }

        $accessUsers = $diary->accessUsers()
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.phone')
            ->get()
            ->map(function ($accessUser) {
                return [
                    'id' => $accessUser->id,
                    'first_name' => $accessUser->first_name,
                    'last_name' => $accessUser->last_name,
                    'phone' => $accessUser->phone,
                    'permission' => $accessUser->pivot->permission,
                    'status' => $accessUser->pivot->status,
                    'granted_at' => $accessUser->pivot->created_at,
                ];
            });

        return response()->json($accessUsers, 200);
    }

    /**
     * Check if user can access the patient.
     */
    private function canAccessPatient($user, Patient $patient): bool
    {
        // ВАЖНО: Сначала проверяем принадлежность к организации
        // Сотрудник организации (приоритет выше, чем type)
        if ($user->organization_id) {
            $organization = Organization::find($user->organization_id);
            
            if (!$organization) {
                return false;
            }

            // Пациент должен принадлежать той же организации
            if ($patient->organization_id !== $organization->id) {
                return false;
            }

            // Владельцы и админы организации имеют доступ ко всем пациентам организации
            if ($user->hasAnyRole(['owner', 'admin', 'manager'])) {
                return true;
            }

            // Пансионат: ВСЕ сотрудники (включая врачей и сиделок) видят ВСЕХ пациентов организации
            if ($organization->isBoardingHouse()) {
                return true;
            }

            // Агентство: только сотрудники, явно добавленные через admin
            if ($organization->isAgency()) {
                // Admin и Manager видят всех
                if ($user->hasAnyRole(['admin', 'manager'])) {
                    return true;
                }
                
                // Проверяем назначение через patient_user
                $isAssigned = $patient->assignedUsers()->where('user_id', $user->id)->exists();
                if ($isAssigned) {
                    return true;
                }
                
                // Проверяем доступ через diary_access
                $hasDiaryAccess = $patient->diary && $patient->diary->hasAccess($user);
                return $hasDiaryAccess;
            }
            
            return true;
        }

        // Приоритет ролям над типом пользователя
        // Пользователь с ролью сиделки/врача (независимо от type)
        if ($user->hasAnyRole(['caregiver', 'doctor'])) {
            // Проверяем, назначен ли к пациенту
            $isAssigned = $patient->assignedUsers()->where('user_id', $user->id)->exists();
            if ($isAssigned) {
                return true;
            }
            
            // Проверяем доступ через дневник
            $hasDiaryAccess = $patient->diary && $patient->diary->hasAccess($user);
            if ($hasDiaryAccess) {
                return true;
            }
            
            // Частная сиделка (без организации) - только назначенные
            if ($user->isPrivateCaregiver()) {
                return $isAssigned || $hasDiaryAccess;
            }
            
            return false;
        }

        // Клиент: проверяем владение или создание пациента
        if ($user->isClient()) {
            // Владелец пациента
            if ($patient->owner_id === $user->id) {
                return true;
            }
            
            // Создатель пациента
            if ($patient->creator_id === $user->id) {
                return true;
            }
            
            return false;
        }

        return false;
    }
    
    /**
     * Определяет тип записи по ключу показателя
     */
    private function getTypeByKey(string $key): string
    {
        // Маппинг ключей к типам
        $keyToType = [
            // Physical - Физикальные параметры
            'temperature' => 'physical',
            'blood_pressure' => 'physical',
            'pulse' => 'physical',
            'saturation' => 'physical',
            'oxygen_saturation' => 'physical',
            'blood_sugar' => 'physical',
            'respiratory_rate' => 'physical',
            'weight' => 'physical',
            
            // Care - Уход
            'meal' => 'care',
            'medicine' => 'care',
            'medication' => 'care',
            'vitamins' => 'care',
            'diaper_change' => 'care',
            'hygiene' => 'care',
            'skin_moisturizing' => 'care',
            'walk' => 'care',
            'progulka' => 'care',
            'cognitive_games' => 'care',
            'sleep' => 'care',
            'care_procedure' => 'care',
            'task_completion' => 'care',
            
            // Excretion - Выделения
            'urination' => 'excretion',
            'urine' => 'excretion',
            'defecation' => 'excretion',
            
            // Symptom - Симптомы
            'pain_level' => 'symptom',
            'nausea' => 'symptom',
            'vomiting' => 'symptom',
            'dyspnea' => 'symptom',
            'itching' => 'symptom',
            'cough' => 'symptom',
            'dry_mouth' => 'symptom',
            'hiccups' => 'symptom',
            'taste_disorder' => 'symptom',
        ];
        
        return $keyToType[$key] ?? 'care'; // По умолчанию 'care'
    }
}
