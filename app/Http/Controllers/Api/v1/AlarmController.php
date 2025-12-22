<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Alarm;
use App\Models\Diary;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Alarms",
 *     description="API endpoints for medication and vitamin alarms"
 * )
 */
class AlarmController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/alarms",
     *     tags={"Alarms"},
     *     summary="Get all alarms for a diary",
     *     description="Retrieve all alarms for a specific diary. Access is restricted based on user permissions.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="diary_id",
     *         in="query",
     *         required=true,
     *         description="Diary ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alarms retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="diary_id", type="integer", example=1),
     *                 @OA\Property(property="creator_id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Парацетамол"),
     *                 @OA\Property(property="type", type="string", enum={"medicine", "vitamin"}, example="medicine"),
     *                 @OA\Property(property="days_of_week", type="array", @OA\Items(type="integer"), example={1,2,3,4,5}),
     *                 @OA\Property(property="times", type="array", @OA\Items(type="string"), example={"09:00", "21:00"}),
     *                 @OA\Property(property="dosage", type="string", nullable=true, example="1 таблетка"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="После еды"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
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
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'diary_id' => 'required|exists:diaries,id',
        ]);

        $user = $request->user();
        $diary = Diary::with('patient')->find($request->diary_id);

        if (!$diary) {
            return response()->json([
                'message' => 'Diary not found.',
            ], 404);
        }

        if (!$user->canAccessDiary($diary)) {
            return response()->json([
                'message' => 'You do not have access to this diary.',
            ], 403);
        }

        $alarms = Alarm::where('diary_id', $diary->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($alarms, 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/alarms/{id}",
     *     tags={"Alarms"},
     *     summary="Get a single alarm by ID",
     *     description="Retrieve a specific alarm. Access is restricted based on user permissions.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Alarm ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alarm retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="diary_id", type="integer", example=1),
     *             @OA\Property(property="creator_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Парацетамол"),
     *             @OA\Property(property="type", type="string", enum={"medicine", "vitamin"}, example="medicine"),
     *             @OA\Property(property="days_of_week", type="array", @OA\Items(type="integer"), example={1,2,3,4,5}),
     *             @OA\Property(property="times", type="array", @OA\Items(type="string"), example={"09:00", "21:00"}),
     *             @OA\Property(property="dosage", type="string", nullable=true, example="1 таблетка"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="После еды"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Alarm not found"
     *     )
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $alarm = Alarm::with('diary.patient')->find($id);

        if (!$alarm) {
            return response()->json([
                'message' => 'Alarm not found.',
            ], 404);
        }

        if (!$user->canAccessDiary($alarm->diary)) {
            return response()->json([
                'message' => 'You do not have access to this alarm.',
            ], 403);
        }

        return response()->json($alarm, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/alarms",
     *     tags={"Alarms"},
     *     summary="Create a new alarm",
     *     description="Create a new medication or vitamin alarm for a diary.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"diary_id", "name", "type", "days_of_week", "times"},
     *             @OA\Property(property="diary_id", type="integer", example=1, description="Diary ID"),
     *             @OA\Property(property="name", type="string", example="Парацетамол", description="Alarm name (medicine/vitamin name)"),
     *             @OA\Property(property="type", type="string", enum={"medicine", "vitamin"}, example="medicine", description="Alarm type"),
     *             @OA\Property(property="days_of_week", type="array", @OA\Items(type="integer"), example={1,2,3,4,5,6,7}, description="Days of week (1=Monday, 7=Sunday)"),
     *             @OA\Property(property="times", type="array", @OA\Items(type="string"), example={"09:00", "14:00", "21:00"}, description="Times for the alarm (HH:MM format, max 4)"),
     *             @OA\Property(property="dosage", type="string", nullable=true, example="1 таблетка", description="Dosage information"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="После еды", description="Additional notes"),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Whether the alarm is active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Alarm created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="diary_id", type="integer", example=1),
     *             @OA\Property(property="creator_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Парацетамол"),
     *             @OA\Property(property="type", type="string", example="medicine"),
     *             @OA\Property(property="days_of_week", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="times", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="dosage", type="string", nullable=true),
     *             @OA\Property(property="notes", type="string", nullable=true),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'diary_id' => 'required|exists:diaries,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:medicine,vitamin',
            'days_of_week' => 'required|array|min:1|max:7',
            'days_of_week.*' => 'required|integer|min:1|max:7',
            'times' => 'required|array|min:1|max:4',
            'times.*' => 'required|string|date_format:H:i',
            'dosage' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $diary = Diary::with('patient')->find($request->diary_id);

        if (!$user->canAccessDiary($diary)) {
            return response()->json([
                'message' => 'You do not have access to this diary.',
            ], 403);
        }

        $alarm = Alarm::create([
            'diary_id' => $diary->id,
            'creator_id' => $user->id,
            'name' => $request->name,
            'type' => $request->type,
            'days_of_week' => $request->days_of_week,
            'times' => $request->times,
            'dosage' => $request->dosage,
            'notes' => $request->notes,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json($alarm, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/alarms/{id}",
     *     tags={"Alarms"},
     *     summary="Update an alarm",
     *     description="Update an existing alarm.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Alarm ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Парацетамол"),
     *             @OA\Property(property="type", type="string", enum={"medicine", "vitamin"}, example="medicine"),
     *             @OA\Property(property="days_of_week", type="array", @OA\Items(type="integer"), example={1,2,3,4,5,6,7}),
     *             @OA\Property(property="times", type="array", @OA\Items(type="string"), example={"09:00", "21:00"}),
     *             @OA\Property(property="dosage", type="string", nullable=true, example="1 таблетка"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="После еды"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alarm updated successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Alarm not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $alarm = Alarm::with('diary.patient')->find($id);

        if (!$alarm) {
            return response()->json([
                'message' => 'Alarm not found.',
            ], 404);
        }

        if (!$user->canAccessDiary($alarm->diary)) {
            return response()->json([
                'message' => 'You do not have access to this alarm.',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:medicine,vitamin',
            'days_of_week' => 'sometimes|array|min:1|max:7',
            'days_of_week.*' => 'required_with:days_of_week|integer|min:1|max:7',
            'times' => 'sometimes|array|min:1|max:4',
            'times.*' => 'required_with:times|string|date_format:H:i',
            'dosage' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        $alarm->update($request->only([
            'name',
            'type',
            'days_of_week',
            'times',
            'dosage',
            'notes',
            'is_active',
        ]));

        return response()->json($alarm, 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/alarms/{id}",
     *     tags={"Alarms"},
     *     summary="Delete an alarm",
     *     description="Delete an existing alarm.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Alarm ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alarm deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Alarm deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Alarm not found"
     *     )
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $alarm = Alarm::with('diary.patient')->find($id);

        if (!$alarm) {
            return response()->json([
                'message' => 'Alarm not found.',
            ], 404);
        }

        if (!$user->canAccessDiary($alarm->diary)) {
            return response()->json([
                'message' => 'You do not have access to this alarm.',
            ], 403);
        }

        $alarm->delete();

        return response()->json([
            'message' => 'Alarm deleted successfully.',
        ], 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/alarms/{id}/toggle",
     *     tags={"Alarms"},
     *     summary="Toggle alarm active status",
     *     description="Toggle the active status of an alarm.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Alarm ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alarm status toggled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="is_active", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Alarm deactivated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Alarm not found"
     *     )
     * )
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $alarm = Alarm::with('diary.patient')->find($id);

        if (!$alarm) {
            return response()->json([
                'message' => 'Alarm not found.',
            ], 404);
        }

        if (!$user->canAccessDiary($alarm->diary)) {
            return response()->json([
                'message' => 'You do not have access to this alarm.',
            ], 403);
        }

        $alarm->update([
            'is_active' => !$alarm->is_active,
        ]);

        return response()->json([
            'id' => $alarm->id,
            'is_active' => $alarm->is_active,
            'message' => $alarm->is_active ? 'Alarm activated.' : 'Alarm deactivated.',
        ], 200);
    }


}
