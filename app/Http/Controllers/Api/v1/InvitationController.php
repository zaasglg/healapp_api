<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Invitations",
 *     description="API для управления приглашениями"
 * )
 */
class InvitationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/invitations",
     *     tags={"Invitations"},
     *     summary="Список приглашений организации",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(type="string", enum={"pending", "accepted", "expired", "revoked"})
     *     ),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->canManageEmployees()) {
            return response()->json(['message' => 'Недостаточно прав'], 403);
        }

        $query = Invitation::forOrganization($user->organization_id)
            ->with(['inviter', 'invitee'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $invitations = $query->paginate(20);

        return response()->json($invitations);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invitations/employee",
     *     tags={"Invitations"},
     *     summary="Создать приглашение для сотрудника",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"role"},
     *             @OA\Property(property="role", type="string", enum={"admin", "doctor", "caregiver"}, example="caregiver")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Приглашение создано",
     *         @OA\JsonContent(
     *             @OA\Property(property="invitation", type="object"),
     *             @OA\Property(property="invite_url", type="string", example="https://app.com/invite/abc123...")
     *         )
     *     )
     * )
     */
    public function createEmployeeInvite(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|in:admin,doctor,caregiver',
        ]);

        $user = $request->user();

        if (!$user->canManageEmployees()) {
            return response()->json(['message' => 'Недостаточно прав'], 403);
        }

        if (!$user->organization_id) {
            return response()->json(['message' => 'У вас нет организации'], 404);
        }

        $invitation = Invitation::create([
            'organization_id' => $user->organization_id,
            'inviter_id' => $user->id,
            'token' => Invitation::generateToken(),
            'type' => Invitation::TYPE_EMPLOYEE,
            'role' => $request->role,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'invitation' => $invitation,
            'invite_url' => $invitation->getInviteUrl(),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invitations/client",
     *     tags={"Invitations"},
     *     summary="Создать приглашение для клиента",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"patient_id"},
     *             @OA\Property(property="patient_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Приглашение создано")
     * )
     */
    public function createClientInvite(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $user = $request->user();

        if (!$user->canManageAccess()) {
            return response()->json(['message' => 'Недостаточно прав'], 403);
        }

        $invitation = Invitation::create([
            'organization_id' => $user->organization_id,
            'inviter_id' => $user->id,
            'token' => Invitation::generateToken(),
            'type' => Invitation::TYPE_CLIENT,
            'patient_id' => $request->patient_id,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'invitation' => $invitation,
            'invite_url' => $invitation->getInviteUrl(),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invitations/{token}",
     *     tags={"Invitations"},
     *     summary="Получить информацию о приглашении по токену",
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Информация о приглашении",
     *         @OA\JsonContent(
     *             @OA\Property(property="organization_name", type="string"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="role", type="string", nullable=true),
     *             @OA\Property(property="expires_at", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function show(string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)
            ->with(['organization:id,name,type'])
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Приглашение не найдено'], 404);
        }

        if (!$invitation->isValid()) {
            return response()->json([
                'message' => 'Приглашение истекло или уже использовано',
                'status' => $invitation->status,
            ], 410);
        }

        return response()->json([
            'organization_name' => $invitation->organization->name,
            'organization_type' => $invitation->organization->type,
            'type' => $invitation->type,
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invitations/{token}/accept",
     *     tags={"Invitations"},
     *     summary="Принять приглашение (регистрация или привязка)",
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "password"},
     *             @OA\Property(property="phone", type="string", example="79001234567"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="first_name", type="string", nullable=true),
     *             @OA\Property(property="last_name", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Приглашение принято",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     )
     * )
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)->first();

        if (!$invitation) {
            return response()->json(['message' => 'Приглашение не найдено'], 404);
        }

        if (!$invitation->isValid()) {
            return response()->json([
                'message' => 'Приглашение истекло или уже использовано',
            ], 410);
        }

        // Проверяем, существует ли пользователь
        $existingUser = User::where('phone', $request->phone)->first();

        if ($existingUser) {
            // Пользователь уже зарегистрирован - проверяем пароль
            $request->validate([
                'phone' => 'required|string',
                'password' => 'required|string',
            ]);

            if (!Hash::check($request->password, $existingUser->password)) {
                return response()->json(['message' => 'Неверный пароль'], 401);
            }

            $user = $existingUser;
        } else {
            // Новая регистрация
            $request->validate([
                'phone' => 'required|string|unique:users,phone',
                'password' => 'required|string|min:6|confirmed',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
            ]);

            $userType = $invitation->isClientInvite() 
                ? UserType::CLIENT 
                : UserType::CLIENT; // Сотрудники тоже CLIENT по user_type

            $user = User::create([
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'type' => $userType->value,
                'phone_verified_at' => now(), // Авто-верификация при приглашении
            ]);
        }

        // Обрабатываем приглашение по типу
        if ($invitation->isEmployeeInvite()) {
            // Привязываем к организации и назначаем роль
            $user->organization_id = $invitation->organization_id;
            $user->save();
            $user->syncRoles([$invitation->role]);
        } elseif ($invitation->isClientInvite() && $invitation->patient_id) {
            // Привязываем клиента к карточке подопечного
            $invitation->patient->update(['owner_id' => $user->id]);
            
            // Даём доступ к дневнику, если есть
            if ($invitation->patient->diary) {
                $invitation->patient->diary->grantAccess($user, 'view');
            }
        }

        // Отмечаем приглашение как принятое
        $invitation->markAsAccepted($user);

        // Создаём токен
        $accessToken = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Приглашение принято',
            'access_token' => $accessToken,
            'user' => $user->fresh(['organization']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/invitations/{id}",
     *     tags={"Invitations"},
     *     summary="Отозвать приглашение",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Приглашение отозвано")
     * )
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->canManageEmployees()) {
            return response()->json(['message' => 'Недостаточно прав'], 403);
        }

        $invitation = Invitation::where('id', $id)
            ->where('organization_id', $user->organization_id)
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Приглашение не найдено'], 404);
        }

        $invitation->markAsRevoked();

        return response()->json(['message' => 'Приглашение отозвано']);
    }
}
