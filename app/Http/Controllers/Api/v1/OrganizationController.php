<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignPatientRequest;
use App\Http\Requests\InviteEmployeeRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Organization",
 *     description="API endpoints for organization management"
 * )
 */
class OrganizationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/organization/employees",
     *     tags={"Organization"},
     *     summary="Get organization employees",
     *     description="Retrieve all employees (users) belonging to the authenticated manager's organization.",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Employees retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="first_name", type="string", example="Maria"),
     *                 @OA\Property(property="last_name", type="string", example="Ivanova"),
     *                 @OA\Property(property="middle_name", type="string", nullable=true, example="Petrovna"),
     *                 @OA\Property(property="phone", type="string", example="9876543210"),
     *                 @OA\Property(property="organization_id", type="integer", example=1),
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
     *         description="Access denied - user is not a manager",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You must be a manager to access this resource.")
     *         )
     *     )
     * )
     */
    public function getEmployees(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only managers can access employees
        if (!$user->hasRole('manager')) {
            return response()->json([
                'message' => 'You must be a manager to access this resource.',
            ], 403);
        }

        $organization = $user->organization;

        if (!$organization) {
            return response()->json([
                'message' => 'You do not have an organization.',
            ], 404);
        }

        $employees = User::where('organization_id', $organization->id)->get();

        return response()->json($employees, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/organization/invite-employee",
     *     tags={"Organization"},
     *     summary="Invite a new employee",
     *     description="Create a new employee (doctor or caregiver) and assign them to the manager's organization. Returns a temporary password for testing.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "role"},
     *             @OA\Property(property="phone", type="string", example="9876543210", description="Employee's phone number (must be unique)"),
     *             @OA\Property(property="first_name", type="string", nullable=true, example="Maria", description="Employee's first name (optional)"),
     *             @OA\Property(property="last_name", type="string", nullable=true, example="Ivanova", description="Employee's last name (optional)"),
     *             @OA\Property(property="middle_name", type="string", nullable=true, example="Petrovna", description="Employee's middle name"),
     *             @OA\Property(property="role", type="string", example="caregiver", description="Employee role", enum={"doctor", "caregiver"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="first_name", type="string", example="Maria"),
     *                 @OA\Property(property="last_name", type="string", example="Ivanova"),
     *                 @OA\Property(property="phone", type="string", example="9876543210"),
     *                 @OA\Property(property="organization_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="password", type="string", example="temp123456", description="Temporary password for testing (will be sent via SMS in production)")
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
     *         description="Access denied - user is not a manager",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You must be a manager to invite employees.")
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
    public function inviteEmployee(InviteEmployeeRequest $request): JsonResponse
    {
        $user = $request->user();

        // Only managers can invite employees
        if (!$user->hasRole('manager')) {
            return response()->json([
                'message' => 'You must be a manager to invite employees.',
            ], 403);
        }

        $organization = $user->organization;

        if (!$organization) {
            return response()->json([
                'message' => 'You do not have an organization.',
            ], 404);
        }

        // Generate a random password
        $password = Str::random(12);

        $employee = User::create([
            'phone' => $request->phone,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'password' => Hash::make($password),
            'organization_id' => $organization->id,
            'phone_verified_at' => now(), // Auto-verify for employees
        ]);

        // Assign role
        $employee->assignRole($request->role);

        return response()->json([
            'user' => $employee,
            'password' => $password, // Return password for testing (will be sent via SMS in production)
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/organization/assign-patient",
     *     tags={"Organization"},
     *     summary="Assign a patient to an employee",
     *     description="Assign a patient to a caregiver or doctor. Both must belong to the same organization.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"patient_id", "user_id"},
     *             @OA\Property(property="patient_id", type="integer", example=1, description="Patient ID"),
     *             @OA\Property(property="user_id", type="integer", example=2, description="Employee (caregiver/doctor) ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Patient assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Patient assigned to employee successfully"),
     *             @OA\Property(property="patient", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="Ivan"),
     *                 @OA\Property(property="last_name", type="string", example="Petrov")
     *             ),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="first_name", type="string", example="Maria"),
     *                 @OA\Property(property="last_name", type="string", example="Ivanova")
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
     *             @OA\Property(property="message", type="string", example="You do not have permission to assign patients.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or business logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Patient and employee must belong to the same organization.")
     *         )
     *     )
     * )
     */
    public function assignPatient(AssignPatientRequest $request): JsonResponse
    {
        $user = $request->user();

        // Only managers can assign patients
        if (!$user->hasRole('manager')) {
            return response()->json([
                'message' => 'You do not have permission to assign patients.',
            ], 403);
        }

        $organization = $user->organization;

        if (!$organization) {
            return response()->json([
                'message' => 'You do not have an organization.',
            ], 404);
        }

        $patient = Patient::findOrFail($request->patient_id);
        $employee = User::findOrFail($request->user_id);

        // Check that patient belongs to the organization
        if ($patient->organization_id !== $organization->id) {
            return response()->json([
                'message' => 'Patient does not belong to your organization.',
            ], 422);
        }

        // Check that employee belongs to the organization
        if ($employee->organization_id !== $organization->id) {
            return response()->json([
                'message' => 'Employee does not belong to your organization.',
            ], 422);
        }

        // Check that employee is a caregiver or doctor
        if (!$employee->hasAnyRole(['caregiver', 'doctor'])) {
            return response()->json([
                'message' => 'User must be a caregiver or doctor to be assigned to patients.',
            ], 422);
        }

        // Attach patient to employee (check if already attached)
        if (!$patient->assignedUsers()->where('user_id', $employee->id)->exists()) {
            $patient->assignedUsers()->attach($employee->id);
        }

        return response()->json([
            'message' => 'Patient assigned to employee successfully',
            'patient' => $patient->only(['id', 'first_name', 'last_name']),
            'user' => $employee->only(['id', 'first_name', 'last_name']),
        ], 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/organization",
     *     tags={"Organization"},
     *     summary="Update organization details",
     *     description="Update the organization's name, address, and/or phone. Only the manager (owner) can update.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", nullable=true, example="New Organization Name", description="Organization name"),
     *             @OA\Property(property="address", type="string", nullable=true, example="123 Main St, City", description="Organization address"),
     *             @OA\Property(property="phone", type="string", nullable=true, example="1234567890", description="Organization phone number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization updated successfully"),
     *             @OA\Property(property="organization", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="New Organization Name"),
     *                 @OA\Property(property="type", type="string", example="pansionat"),
     *                 @OA\Property(property="phone", type="string", example="1234567890"),
     *                 @OA\Property(property="address", type="string", example="123 Main St, City"),
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
     *         description="Access denied - user is not a manager",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You must be a manager to update the organization.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You do not have an organization.")
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
    public function update(UpdateOrganizationRequest $request): JsonResponse
    {
        $user = $request->user();

        // Only managers can update organization
        if (!$user->hasRole('manager')) {
            return response()->json([
                'message' => 'You must be a manager to update the organization.',
            ], 403);
        }

        $organization = $user->organization;

        if (!$organization) {
            return response()->json([
                'message' => 'You do not have an organization.',
            ], 404);
        }

        // Update only provided fields
        $organization->update($request->only(['name', 'address', 'phone']));

        return response()->json([
            'message' => 'Organization updated successfully',
            'organization' => $organization,
        ], 200);
    }
}
