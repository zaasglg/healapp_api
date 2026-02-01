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
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *
     *         @OA\Schema(type="string", enum={"pending", "accepted", "expired", "revoked"})
     *     ),
     *
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
     *     description="Создает приглашение для сотрудника организации. Возвращает deeplink URL в формате /invite/{token}.",
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"role"},
     *
     *             @OA\Property(property="role", type="string", enum={"admin", "manager", "doctor", "caregiver"}, example="caregiver", description="Роль сотрудника в организации")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Приглашение создано",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Приглашение создано"),
     *             @OA\Property(property="invitation", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="role", type="string"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time"),
     *                 @OA\Property(property="organization_name", type="string")
     *             ),
     *             @OA\Property(property="invite_url", type="string", example="https://app.com/invite/abc123...")
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Недостаточно прав"),
     *     @OA\Response(response=404, description="У пользователя нет организации")
     * )
     */
    public function createEmployeeInvite(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|in:admin,manager,doctor,caregiver',
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
            'message' => 'Приглашение создано',
            'invitation' => [
                'id' => $invitation->id,
                'organization_id' => $invitation->organization_id,
                'inviter_id' => $invitation->inviter_id,
                'invitee_id' => $invitation->invitee_id,
                'token' => $invitation->token,
                'type' => $invitation->type,
                'role' => $invitation->role,
                'patient_id' => $invitation->patient_id,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at,
                'accepted_at' => $invitation->accepted_at,
                'created_at' => $invitation->created_at,
                'updated_at' => $invitation->updated_at,
                'organization_name' => $user->organization->name,
            ],
            'invite_url' => $invitation->getInviteUrl(),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invitations/client",
     *     tags={"Invitations"},
     *     summary="Создать приглашение для клиента",
     *     description="Создает приглашение для клиента. Возвращает deeplink URL в формате /client-invite/{token}.",
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"patient_id"},
     *
     *             @OA\Property(property="patient_id", type="integer", example=1)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Приглашение создано",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="invitation", type="object"),
     *             @OA\Property(property="invite_url", type="string", example="https://app.com/client-invite/abc123...")
     *         )
     *     )
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
     * @OA\Post(
     *     path="/api/v1/invitations/diary",
     *     tags={"Invitations"},
     *     summary="Создать ссылку-приглашение в дневник",
     *     description="Клиент создает ссылку, по которой организация или частный специалист могут получить доступ к дневнику.",
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"patient_id"},
     *             @OA\Property(property="patient_id", type="integer", example=1)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Приглашение создано",
     *
     *         @OA\JsonContent(
     *             @OA\Property(property="invite_url", type="string", example="https://app.com/diary-invite/abc123...")
     *         )
     *     )
     * )
     */
    public function createDiaryInvite(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $user = $request->user();
        $patient = \App\Models\Patient::find($request->patient_id);

        // Только владелец (клиент) или создатель может создавать приглашение
        if ($patient->owner_id !== $user->id && $patient->creator_id !== $user->id) {
            return response()->json(['message' => 'Недостаточно прав'], 403);
        }

        // Создаем приглашение
        $invitation = Invitation::create([
            'inviter_id' => $user->id,
            'token' => Invitation::generateToken(),
            'type' => Invitation::TYPE_DIARY_ACCESS,
            'patient_id' => $patient->id,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7), // Ссылка действительна 7 дней
        ]);

        return response()->json([
            'message' => 'Ссылка создана',
            'invite_url' => $invitation->getInviteUrl(), // Нужно убедиться, что Invitation::getInviteUrl поддерживает новый тип
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invitations/{token}",
     *     tags={"Invitations"},
     *     summary="Получить информацию о приглашении по токену",
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Информация о приглашении",
     *
     *         @OA\JsonContent(
     *
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
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"phone", "password"},
     *
     *             @OA\Property(property="phone", type="string", example="79001234567"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="first_name", type="string", nullable=true),
     *             @OA\Property(property="last_name", type="string", nullable=true),
     *             @OA\Property(property="type", type="string", nullable=true, enum={"client", "private_caregiver", "organization", "pansionat", "agency"})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Приглашение принято",
     *
     *         @OA\JsonContent(
     *
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
                'type' => 'nullable|string|in:client,organization,private_caregiver,pansionat,agency',
            ]);

            // Determine user type
            if ($invitation->isClientInvite()) {
                $userType = UserType::CLIENT;
            } elseif ($invitation->isDiaryAccessInvite()) {
                $userType = match ($request->type) {
                    'client' => UserType::CLIENT,
                    'organization', 'pansionat', 'agency' => UserType::ORGANIZATION,
                    'private_caregiver' => UserType::PRIVATE_CAREGIVER,
                    null => UserType::PRIVATE_CAREGIVER,
                    default => UserType::PRIVATE_CAREGIVER,
                };
            } else {
                $userType = UserType::ORGANIZATION;
            }

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
            // Привязываем к организации и назначаем роль через метод addEmployee
            $organization = Organization::find($invitation->organization_id);
            if ($organization) {
                $organization->addEmployee($user, $invitation->role);
            }
        } elseif ($invitation->isClientInvite() && $invitation->patient_id) {
            // Привязываем клиента к карточке подопечного
            $invitation->patient->update(['owner_id' => $user->id]);

            // Даём доступ к дневнику, если есть
            if ($invitation->patient->diary) {
                $invitation->patient->diary->grantAccess($user, 'view'); // Or 'full'? Usually clients have full access.
                // Clients usually are owners, so they have implicit access. 
                // But explicit access doesn't hurt.
            }
        } elseif ($invitation->isDiaryAccessInvite() && $invitation->patient_id) {
            // For diary invite:
            // 1. Grant explicit access
            if ($invitation->patient->diary) {
                // Grant 'full' access to the specialist/agency rep
                $invitation->patient->diary->grantAccess($user, 'full');
            }

            // 2. If User belongs to Organization, link patient to Organization
            // This allows the Organization logic (Access Policies) to work better.
            if ($user->organization_id) {
                $invitation->patient->update(['organization_id' => $user->organization_id]);
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
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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
