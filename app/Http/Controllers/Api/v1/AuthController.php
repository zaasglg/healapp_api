<?php

namespace App\Http\Controllers\Api\v1;

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
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API endpoints for user authentication"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     description="Register a new user with phone number and password. A verification code will be sent via SMS.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "password", "password_confirmation", "account_type"},
     *             @OA\Property(property="first_name", type="string", nullable=true, example="John", description="User's first name (optional)"),
     *             @OA\Property(property="last_name", type="string", nullable=true, example="Doe", description="User's last name (optional)"),
     *             @OA\Property(property="middle_name", type="string", nullable=true, example="Michael", description="User's middle name (optional)"),
     *             @OA\Property(property="phone", type="string", example="1234567890", description="User's phone number (must be unique)"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="User's password (minimum 6 characters)"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123", description="Password confirmation"),
             @OA\Property(property="account_type", type="string", example="client", description="Account type: client, specialist, pansionat, or agency", enum={"client", "specialist", "pansionat", "agency"}),
             @OA\Property(property="organization_name", type="string", nullable=true, example="Health Care Center", description="Organization name (optional, used for pansionat or agency)"),
             @OA\Property(property="address", type="string", nullable=true, example="123 Main St, City", description="Address (optional, used for organizations)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Registration successful. SMS sent.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SMS sent"),
     *             @OA\Property(property="phone", type="string", example="1234567890")
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
    public function register(RegisterRequest $request): JsonResponse
    {
        $verificationCode = str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // Step 1: Create the User
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'verification_code' => $verificationCode,
        ]);

        // Step 2: Assign Role based on account_type
        $roleMap = [
            'client' => 'client',
            'specialist' => 'specialist',
            'pansionat' => 'manager',
            'agency' => 'manager',
        ];

        $roleName = $roleMap[$request->account_type] ?? 'client';
        $user->assignRole($roleName);

        // Step 3: Create Organization (only if pansionat or agency)
        if (in_array($request->account_type, ['pansionat', 'agency'])) {
            Organization::create([
                'owner_id' => $user->id,
                'name' => $request->organization_name,
                'type' => $request->account_type,
                'phone' => $request->phone, // Use user's phone as organization contact
                'address' => $request->address,
            ]);
        }

        // TODO: Implement actual SMS sending logic here
        // For now, we're just mocking it

        return response()->json([
            'message' => 'SMS sent',
            'phone' => $user->phone,
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/verify-phone",
     *     tags={"Authentication"},
     *     summary="Verify phone number with code",
     *     description="Verify the user's phone number using the verification code sent via SMS. Returns an access token upon successful verification.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "code"},
     *             @OA\Property(property="phone", type="string", example="1234567890", description="User's phone number"),
     *             @OA\Property(property="code", type="string", example="1234", description="4-digit verification code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Phone verified successfully. Access token issued.",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", example="1|abcdef123456..."),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="middle_name", type="string", nullable=true, example="Michael"),
     *                 @OA\Property(property="phone", type="string", example="1234567890"),
     *                 @OA\Property(property="phone_verified_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid verification code",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid verification code")
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
    public function verifyPhone(VerifyPhoneRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if ($user->verification_code !== $request->code) {
            return response()->json([
                'message' => 'Invalid verification code',
            ], 401);
        }

        $user->phone_verified_at = now();
        $user->verification_code = null;
        $user->save();

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $user,
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Authentication"},
     *     summary="Login user",
     *     description="Authenticate user with phone and password. Returns an access token if credentials are valid and phone is verified.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "password"},
     *             @OA\Property(property="phone", type="string", example="1234567890", description="User's phone number"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="User's password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", example="1|abcdef123456..."),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="middle_name", type="string", nullable=true, example="Michael"),
     *                 @OA\Property(property="phone", type="string", example="1234567890"),
     *                 @OA\Property(property="phone_verified_at", type="string", format="date-time"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials or phone not verified",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials or phone not verified")
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
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->phone_verified_at) {
            return response()->json([
                'message' => 'Phone number not verified. Please verify your phone number first.',
            ], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $user,
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Authentication"},
     *     summary="Logout user",
     *     description="Revoke the current access token for the authenticated user.",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
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
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     tags={"Authentication"},
     *     summary="Get current authenticated user",
     *     description="Returns the currently authenticated user's information.",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User information retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="middle_name", type="string", nullable=true, example="Michael"),
     *             @OA\Property(property="phone", type="string", example="1234567890"),
     *             @OA\Property(property="phone_verified_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
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
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user(), 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/auth/profile",
     *     tags={"Authentication"},
     *     summary="Update user profile",
     *     description="Update the authenticated user's profile information (first_name, last_name, middle_name). All fields are optional - only provided fields will be updated.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", nullable=true, example="John", description="User's first name"),
     *             @OA\Property(property="last_name", type="string", nullable=true, example="Doe", description="User's last name"),
     *             @OA\Property(property="middle_name", type="string", nullable=true, example="Michael", description="User's middle name")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="middle_name", type="string", nullable=true, example="Michael"),
     *             @OA\Property(property="phone", type="string", example="1234567890"),
     *             @OA\Property(property="phone_verified_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Only update fields that are present in the request (respects 'sometimes' validation rule)
        $user->fill($request->only(['first_name', 'last_name', 'middle_name']));
        $user->save();
        
        return response()->json($user, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/change-phone/request",
     *     tags={"Authentication"},
     *     summary="Request phone number change",
     *     description="Initiate a phone number change by providing a new phone number. A verification code will be sent to the new phone number.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="9876543210", description="New phone number (must be unique and not already in use)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Confirmation code sent to new phone",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Confirmation code sent to new phone")
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function changePhoneRequest(ChangePhoneRequest $request): JsonResponse
    {
        $user = $request->user();
        $verificationCode = str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);

        $user->unverified_phone = $request->phone;
        $user->verification_code = $verificationCode;
        $user->save();

        // TODO: Implement actual SMS sending logic here
        // For now, we're just mocking it

        return response()->json([
            'message' => 'Confirmation code sent to new phone',
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/change-phone/confirm",
     *     tags={"Authentication"},
     *     summary="Confirm phone number change",
     *     description="Confirm the phone number change by providing the verification code sent to the new phone number.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="1234", description="4-digit verification code sent to the new phone number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Phone number updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Phone number updated successfully"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="middle_name", type="string", nullable=true, example="Michael"),
     *                 @OA\Property(property="phone", type="string", example="9876543210"),
     *                 @OA\Property(property="phone_verified_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated or invalid verification code",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid verification code or no pending phone change request")
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
    public function confirmChangePhone(ConfirmChangePhoneRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check if there's a pending phone change request
        if (!$user->unverified_phone || !$user->verification_code) {
            return response()->json([
                'message' => 'Invalid verification code or no pending phone change request',
            ], 401);
        }

        // Verify the code
        if ($user->verification_code !== $request->code) {
            return response()->json([
                'message' => 'Invalid verification code or no pending phone change request',
            ], 401);
        }

        // Move unverified_phone to phone and clear both unverified_phone and verification_code
        $user->phone = $user->unverified_phone;
        $user->unverified_phone = null;
        $user->verification_code = null;
        $user->phone_verified_at = now(); // Re-verify the phone since it's a new number
        $user->save();

        return response()->json([
            'message' => 'Phone number updated successfully',
            'user' => $user,
        ], 200);
    }
}

