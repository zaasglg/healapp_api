<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiaryEntryRequest;
use App\Models\Diary;
use App\Models\DiaryEntry;
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
     *                     @OA\Property(property="type", type="string", example="physical"),
     *                     @OA\Property(property="key", type="string", example="temperature"),
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
     *         description="Diary entry created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="diary_id", type="integer", example=1),
     *             @OA\Property(property="author_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", example="physical"),
     *             @OA\Property(property="key", type="string", example="temperature"),
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
        }

        $data = $request->validated();
        
        // Remove patient_id from data, we'll use diary_id instead
        unset($data['patient_id']);
        $data['diary_id'] = $diary->id;
        
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

        $entry = DiaryEntry::create($data);

        return response()->json($entry, 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/diary/pinned",
     *     tags={"Diary"},
     *     summary="Update pinned parameters for diary",
     *     description="Update the pinned parameters with timers for a patient's diary.",
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
            'pinned_parameters' => 'required|array',
            'pinned_parameters.*.key' => 'required|string',
            'pinned_parameters.*.interval_minutes' => 'nullable|integer|min:1',
            'pinned_parameters.*.times' => 'nullable|array',
            'pinned_parameters.*.settings' => 'nullable|array',
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
        }

        $diary->update([
            'pinned_parameters' => $request->pinned_parameters,
        ]);

        return response()->json([
            'message' => 'Закреплённые параметры успешно обновлены',
            'diary' => $diary,
        ], 200);
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
            // Проверяем доступ через дневник
            $diary = $patient->diary;
            if ($diary && $diary->hasAccess($user)) {
                return true;
            }
            // Или через назначение
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

            // Агентство: проверяем доступ через дневник или назначение
            if ($organization->isAgency()) {
                // Проверяем доступ через дневник
                $diary = $patient->diary;
                if ($diary && $diary->hasAccess($user)) {
                    return true;
                }
                // Или через назначение
                return $patient->assignedUsers()->where('user_id', $user->id)->exists();
            }
        }

        return false;
    }
}
