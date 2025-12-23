<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DiaryEntry;
use App\Models\Patient;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Analytics",
 *     description="API endpoints for analytics and chart data"
 * )
 */
class StatsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/stats/chart",
     *     tags={"Analytics"},
     *     summary="Get diary chart data",
     *     description="Retrieve diary entries for a specific patient and key (e.g., temperature) over a time period. Returns data points for chart visualization.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="patient_id",
     *         in="query",
     *         required=true,
     *         description="Patient ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="key",
     *         in="query",
     *         required=true,
     *         description="Diary entry key (e.g., 'temperature', 'blood_pressure', 'mood')",
     *         @OA\Schema(type="string", example="temperature")
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Time period for data",
     *         @OA\Schema(type="string", enum={"7_days", "30_days"}, default="7_days")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chart data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="patient_id", type="integer", example=1),
     *             @OA\Property(property="key", type="string", example="temperature"),
     *             @OA\Property(property="period", type="string", example="7_days"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="recorded_at", type="string", format="date-time", example="2024-01-01T08:00:00.000000Z"),
     *                     @OA\Property(property="value", type="object", description="JSON value from diary entry", example={"value": "36.6"}),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Normal temperature")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - missing required parameters",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="patient_id and key parameters are required")
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
    public function getDiaryChart(Request $request): JsonResponse
    {
        $patientId = $request->query('patient_id');
        $key = $request->query('key');
        $period = $request->query('period', '7_days');

        if (!$patientId || !$key) {
            return response()->json([
                'message' => 'Параметры patient_id и key обязательны',
            ], 400);
        }

        $patient = Patient::findOrFail($patientId);
        $user = $request->user();

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }

        // Calculate date range based on period
        $days = $period === '30_days' ? 30 : 7;
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Fetch diary entries through diary relationship
        $diary = $patient->diary;
        
        if (!$diary) {
            return response()->json([
                'patient_id' => $patientId,
                'key' => $key,
                'period' => $period,
                'data' => [],
            ], 200);
        }
        
        $entries = $diary->entries()
            ->where('key', $key)
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at', 'asc')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'recorded_at' => $entry->recorded_at->toIso8601String(),
                    'value' => $entry->value, // Return raw JSON value for frontend to parse
                    'notes' => $entry->notes,
                ];
            });

        return response()->json([
            'patient_id' => $patientId,
            'key' => $key,
            'period' => $period,
            'data' => $entries,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stats/tasks",
     *     tags={"Analytics"},
     *     summary="Get task summary statistics",
     *     description="Retrieve task statistics for a patient on a specific date. Returns counts and completion rate.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="patient_id",
     *         in="query",
     *         required=true,
     *         description="Patient ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=false,
     *         description="Date for statistics (YYYY-MM-DD). Defaults to today.",
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="patient_id", type="integer", example=1),
     *             @OA\Property(property="date", type="string", format="date", example="2024-01-01"),
     *             @OA\Property(property="total", type="integer", example=10, description="Total number of tasks"),
     *             @OA\Property(property="completed", type="integer", example=7, description="Number of completed tasks"),
     *             @OA\Property(property="missed", type="integer", example=1, description="Number of missed tasks"),
     *             @OA\Property(property="pending", type="integer", example=2, description="Number of pending tasks"),
     *             @OA\Property(property="completion_rate", type="number", format="float", example=70.0, description="Completion rate as percentage (0-100)")
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
    public function getTaskSummary(Request $request): JsonResponse
    {
        $patientId = $request->query('patient_id');
        $date = $request->query('date', Carbon::today()->format('Y-m-d'));

        if (!$patientId) {
            return response()->json([
                'message' => 'Параметр patient_id обязателен',
            ], 400);
        }

        $patient = Patient::findOrFail($patientId);
        $user = $request->user();

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'У вас нет доступа к этому пациенту.',
            ], 403);
        }

        // Get tasks for the specified date
        $tasks = Task::where('patient_id', $patientId)
            ->whereDate('start_at', $date)
            ->get();

        $total = $tasks->count();
        $completed = $tasks->where('status', 'completed')->count();
        $missed = $tasks->where('status', 'missed')->count();
        $pending = $tasks->where('status', 'pending')->count();
        $cancelled = $tasks->where('status', 'cancelled')->count();

        // Calculate completion rate (completed / (total - cancelled)) * 100
        $completionRate = 0;
        if ($total > 0 && ($total - $cancelled) > 0) {
            $completionRate = round(($completed / ($total - $cancelled)) * 100, 2);
        }

        return response()->json([
            'patient_id' => $patientId,
            'date' => $date,
            'total' => $total,
            'completed' => $completed,
            'missed' => $missed,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'completion_rate' => $completionRate,
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
