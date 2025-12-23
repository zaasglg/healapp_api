<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\OrganizationType;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePhoneRequest;
use App\Http\Requests\Auth\ConfirmChangePhoneRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyPhoneRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API для аутентификации"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     tags={"Authentication"},
     *     summary="Регистрация нового пользователя",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "password", "password_confirmation", "account_type"},
     *             @OA\Property(property="first_name", type="string", nullable=true),
     *             @OA\Property(property="last_name", type="string", nullable=true),
     *             @OA\Property(property="phone", type="string", example="79001234567"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="account_type", type="string", enum={"client", "specialist", "pansionat", "agency"}),
     *             @OA\Property(property="organization_name", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="SMS отправлен")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // В тестовом режиме используем фиксированный код '1234'
        $verificationCode = app()->environment('production')
            ? str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT)
            : '1234';

        // Определяем user_type
        $userType = match ($request->account_type) {
            'client' => UserType::CLIENT,
            'specialist' => UserType::PRIVATE_CAREGIVER,
            'pansionat', 'agency' => UserType::ORGANIZATION,
            default => UserType::CLIENT,
        };

        // Создаём пользователя
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'verification_code' => $verificationCode,
            'type' => $userType->value,
        ]);

        // Если это организация - создаём её и назначаем роль owner
        if (in_array($request->account_type, ['pansionat', 'agency'])) {
            $organizationType = $request->account_type === 'pansionat' 
                ? OrganizationType::BOARDING_HOUSE 
                : OrganizationType::AGENCY;

            $organization = Organization::create([
                'owner_id' => $user->id,
                'name' => $request->organization_name,
                'type' => $organizationType->value,
                'phone' => $request->phone,
                'address' => $request->address,
            ]);

            // Привязываем пользователя к организации
            $user->organization_id = $organization->id;
            $user->save();

            // Назначаем роль owner через Spatie
            $user->assignRole('owner');
        }

        // TODO: Реальная отправка SMS

        return response()->json([
            'message' => 'SMS sent',
            'phone' => $user->phone,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/verify-phone",
     *     tags={"Authentication"},
     *     summary="Подтверждение телефона",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "code"},
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="code", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Телефон подтверждён")
     * )
     */
    public function verifyPhone(VerifyPhoneRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if (!$user || $user->verification_code !== $request->code) {
            return response()->json(['message' => 'Неверный код'], 401);
        }

        $user->phone_verified_at = now();
        $user->verification_code = null;
        $user->save();

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Authentication"},
     *     summary="Вход в систему",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "password"},
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Успешный вход")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Неверные учётные данные'],
            ]);
        }

        if (!$user->phone_verified_at) {
            return response()->json([
                'message' => 'Телефон не подтверждён',
            ], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Authentication"},
     *     summary="Выход из системы",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Успешный выход")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     tags={"Authentication"},
     *     summary="Получить текущего пользователя",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Данные пользователя")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->formatUser($request->user()));
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/auth/profile",
     *     tags={"Authentication"},
     *     summary="Обновить профиль",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string"),
     *             @OA\Property(property="last_name", type="string"),
     *             @OA\Property(property="middle_name", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Профиль обновлён")
     * )
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->only(['first_name', 'last_name', 'middle_name']));
        $user->save();
        
        return response()->json($this->formatUser($user));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/avatar",
     *     tags={"Authentication"},
     *     summary="Загрузить аватар",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="avatar",
     *                     type="string",
     *                     format="binary",
     *                     description="Файл изображения аватара"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Аватар загружен")
     * )
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        $user = $request->user();

        // Удаляем старый аватар, если он существует
        if ($user->avatar) {
            $oldPath = str_replace('/storage/', '', parse_url($user->avatar, PHP_URL_PATH));
            Storage::disk('public')->delete($oldPath);
        }

        // Сохраняем новый аватар
        $path = $request->file('avatar')->store('avatars/' . $user->id, 'public');
        $avatarUrl = Storage::url($path);

        $user->avatar = $avatarUrl;
        $user->save();

        return response()->json([
            'message' => 'Аватар загружен',
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/change-phone/request",
     *     tags={"Authentication"},
     *     summary="Запрос на смену телефона",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Код отправлен")
     * )
     */
    public function changePhoneRequest(ChangePhoneRequest $request): JsonResponse
    {
        $user = $request->user();
        // В тестовом режиме используем фиксированный код '1234'
        $verificationCode = app()->environment('production')
            ? str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT)
            : '1234';

        $user->unverified_phone = $request->phone;
        $user->verification_code = $verificationCode;
        $user->save();

        return response()->json(['message' => 'Код отправлен на новый номер']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/change-phone/confirm",
     *     tags={"Authentication"},
     *     summary="Подтверждение смены телефона",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Телефон изменён")
     * )
     */
    public function confirmChangePhone(ConfirmChangePhoneRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->unverified_phone || !$user->verification_code) {
            return response()->json(['message' => 'Нет запроса на смену номера'], 401);
        }

        if ($user->verification_code !== $request->code) {
            return response()->json(['message' => 'Неверный код'], 401);
        }

        $user->phone = $user->unverified_phone;
        $user->unverified_phone = null;
        $user->verification_code = null;
        $user->phone_verified_at = now();
        $user->save();

        return response()->json([
            'message' => 'Телефон изменён',
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Форматировать пользователя для ответа
     */
    private function formatUser(User $user): array
    {
        $user->load(['organization', 'roles']);

        $data = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'avatar' => $user->avatar,
            'phone' => $user->phone,
            'type' => $user->type?->value,
            'account_type' => $user->account_type,
            'phone_verified_at' => $user->phone_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        // Добавляем роль, если есть
        if ($user->roles->isNotEmpty()) {
            $data['role'] = $user->roles->first()->name;
        }

        // Добавляем организацию, если есть
        if ($user->organization) {
            $data['organization'] = [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'type' => $user->organization->type?->value,
            ];
        }

        return $data;
    }
}
