<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiaryEntryRequest;
use App\Models\DiaryEntry;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Diary",
 *     description="API endpoints for patient diary entries"
 * )
 */
class DiaryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/diary",
     *     tags={"Diary"},
     *     summary="Get diary entries for a patient",
     *     description="Retrieve diary entries for a specific patient. Access is restricted to users who have access to the patient.",
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
     *         description="Diary entries retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="patient_id", type="integer", example=1),
     *                 @OA\Property(property="author_id", type="integer", example=1),
     *                 @OA\Property(property="type", type="string", example="physical", enum={"care", "physical", "excretion", "symptom"}),
     *                 @OA\Property(property="key", type="string", example="temperature"),
     *                 @OA\Property(property="value", type="object", description="Entry value as JSON object"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Normal temperature"),
     *                 @OA\Property(property="recorded_at", type="string", format="date-time", example="2024-01-01T10:00:00.000000Z"),
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
     *             @OA\Property(property="message", type="string", example="You do not have access to this patient.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Patient not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Patient] {id}")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $patientId = $request->query('patient_id');

        if (!$patientId) {
            return response()->json([
                'message' => 'patient_id parameter is required',
            ], 400);
        }

        $patient = Patient::findOrFail($patientId);
        $user = $request->user();

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'You do not have access to this patient.',
            ], 403);
        }

        $query = DiaryEntry::where('patient_id', $patientId)
            ->orderBy('recorded_at', 'desc');

        // Filter by date range if provided
        if ($request->has('from_date')) {
            $query->whereDate('recorded_at', '>=', $request->query('from_date'));
        }

        if ($request->has('to_date')) {
            $query->whereDate('recorded_at', '<=', $request->query('to_date'));
        }

        $entries = $query->get();

        return response()->json($entries, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/diary",
     *     tags={"Diary"},
     *     summary="Create a new diary entry",
     *     description="Create a new diary entry for a patient. Access is restricted to users who have access to the patient.",
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
     *             @OA\Property(property="patient_id", type="integer", example=1),
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
                'message' => 'You do not have access to this patient.',
            ], 403);
        }

        $data = $request->validated();
        
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
