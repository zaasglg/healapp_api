<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="API endpoints for managing user notifications"
 * )
 */
class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/notifications",
     *     tags={"Notifications"},
     *     summary="Get user notifications",
     *     description="Retrieve paginated list of notifications for the authenticated user.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                     @OA\Property(property="type", type="string", example="App\\Notifications\\TaskStatusUpdateNotification"),
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(property="type", type="string", example="task_status_update"),
     *                         @OA\Property(property="task_id", type="integer", example=1),
     *                         @OA\Property(property="task_title", type="string", example="Give medicine"),
     *                         @OA\Property(property="patient_name", type="string", example="Ivan Petrov"),
     *                         @OA\Property(property="status", type="string", example="completed")
     *                     ),
     *                     @OA\Property(property="read_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="total", type="integer", example=50)
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
        $perPage = $request->query('per_page', 15);

        $notifications = $user->notifications()->paginate($perPage);

        return response()->json($notifications, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/{id}/read",
     *     tags={"Notifications"},
     *     summary="Mark notification as read",
     *     description="Mark a specific notification as read.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Notification ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Notification marked as read")
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
     *         response=404,
     *         description="Notification not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Notification not found")
     *         )
     *     )
     * )
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return response()->json([
                'message' => 'Уведомление не найдено',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Уведомление отмечено как прочитанное',
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/read-all",
     *     tags={"Notifications"},
     *     summary="Mark all notifications as read",
     *     description="Mark all unread notifications for the authenticated user as read.",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="All notifications marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="All notifications marked as read"),
     *             @OA\Property(property="count", type="integer", example=5, description="Number of notifications marked as read")
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
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Все уведомления отмечены как прочитанные',
            'count' => $count,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/notifications/unread-count",
     *     tags={"Notifications"},
     *     summary="Get unread notification count",
     *     description="Get the count of unread notifications for the authenticated user.",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Unread count retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="unread_count", type="integer", example=5)
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
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = $user->unreadNotifications()->count();

        return response()->json([
            'unread_count' => $count,
        ], 200);
    }
}
