<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Patients",
 *     description="API endpoints for patient management"
 * )
 */
class PatientController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/patients",
     *     tags={"Patients"},
     *     summary="Get list of patients",
     *     description="Retrieve a list of patients. Clients see their own patients, managers see their organization's patients.",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of patients retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="creator_id", type="integer", example=1),
     *                 @OA\Property(property="organization_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="first_name", type="string", example="Ivan"),
     *                 @OA\Property(property="last_name", type="string", example="Petrov"),
     *                 @OA\Property(property="middle_name", type="string", nullable=true, example="Sergeevich"),
     *                 @OA\Property(property="birth_date", type="string", format="date", nullable=true, example="1950-05-15"),
     *                 @OA\Property(property="gender", type="string", example="male", enum={"male", "female"}),
     *                 @OA\Property(property="weight", type="integer", nullable=true, example=75),
     *                 @OA\Property(property="height", type="integer", nullable=true, example=175),
     *                 @OA\Property(property="mobility", type="string", example="walking", enum={"walking", "sitting", "bedridden"}),
     *                 @OA\Property(property="diagnoses", type="array", nullable=true, @OA\Items(type="string"), example={"Stroke", "Dementia"}),
     *                 @OA\Property(property="needed_services", type="array", nullable=true, @OA\Items(type="string"), example={"Nursing care", "Physical therapy"}),
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

        // Клиент видит только своих пациентов (где owner_id = user.id)
        if ($user->isClient()) {
            $patients = Patient::where('owner_id', $user->id)->get();
        }
        // Частная сиделка видит пациентов, к которым назначена
        elseif ($user->isPrivateCaregiver()) {
            $patients = $user->assignedPatients()->get();
        }
        // Сотрудник организации видит пациентов своей организации
        elseif ($user->organization_id) {
            $organization = $user->organization;
            
            if (!$organization) {
                return response()->json([], 200);
            }

            // Пансионат: все сотрудники видят всех пациентов организации
            if ($organization->isBoardingHouse()) {
                $patients = Patient::where('organization_id', $organization->id)->get();
            }
            // Агентство: только назначенные пациенты
            elseif ($organization->isAgency()) {
                // Владельцы и админы видят всех пациентов организации
                if ($user->hasAnyRole(['owner', 'admin'])) {
                    $patients = Patient::where('organization_id', $organization->id)->get();
                } else {
                    // Остальные сотрудники видят только назначенных пациентов
                    $patients = $user->assignedPatients()
                        ->where('organization_id', $organization->id)
                        ->get();
                }
            } else {
                // Fallback: все пациенты организации для владельцев/админов
                if ($user->hasAnyRole(['owner', 'admin'])) {
                    $patients = Patient::where('organization_id', $organization->id)->get();
                } else {
                    $patients = collect();
                }
            }
        }
        // Если пользователь не принадлежит ни к одной категории
        else {
            $patients = collect();
        }

        return response()->json($patients, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/patients",
     *     tags={"Patients"},
     *     summary="Create a new patient",
     *     description="Create a new patient profile. Clients create personal patients, managers create organization patients.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name", "last_name", "gender", "mobility"},
     *             @OA\Property(property="first_name", type="string", example="Ivan", description="Patient's first name"),
     *             @OA\Property(property="last_name", type="string", example="Petrov", description="Patient's last name"),
     *             @OA\Property(property="middle_name", type="string", nullable=true, example="Sergeevich", description="Patient's middle name"),
     *             @OA\Property(property="birth_date", type="string", format="date", nullable=true, example="1950-05-15", description="Patient's birth date"),
     *             @OA\Property(property="gender", type="string", example="male", description="Patient's gender", enum={"male", "female"}),
     *             @OA\Property(property="weight", type="integer", nullable=true, example=75, description="Weight in kg"),
     *             @OA\Property(property="height", type="integer", nullable=true, example=175, description="Height in cm"),
     *             @OA\Property(property="mobility", type="string", example="walking", description="Mobility status", enum={"walking", "sitting", "bedridden"}),

     *             @OA\Property(property="diagnoses", type="array", nullable=true, @OA\Items(type="string"), example={"Stroke", "Dementia"}, description="Array of diagnoses"),
     *             @OA\Property(property="needed_services", type="array", nullable=true, @OA\Items(type="string"), example={"Nursing care", "Physical therapy"}, description="Array of needed services"),
     *             @OA\Property(property="organization_id", type="integer", nullable=true, example=1, description="Organization ID (optional, defaults to user's organization)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Patient created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="creator_id", type="integer", example=1),
     *             @OA\Property(property="organization_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="first_name", type="string", example="Ivan"),
     *             @OA\Property(property="last_name", type="string", example="Petrov"),
     *             @OA\Property(property="middle_name", type="string", nullable=true, example="Sergeevich"),
     *             @OA\Property(property="birth_date", type="string", format="date", nullable=true, example="1950-05-15"),
     *             @OA\Property(property="gender", type="string", example="male"),
     *             @OA\Property(property="weight", type="integer", nullable=true, example=75),
     *             @OA\Property(property="height", type="integer", nullable=true, example=175),
     *             @OA\Property(property="mobility", type="string", example="walking"),
     *             @OA\Property(property="diagnoses", type="array", nullable=true, @OA\Items(type="string"), example={"Stroke", "Dementia"}),
     *             @OA\Property(property="needed_services", type="array", nullable=true, @OA\Items(type="string"), example={"Nursing care", "Physical therapy"}),
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
    public function store(StorePatientRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Set creator_id to authenticated user
        $data['creator_id'] = $user->id;

        // Determine organization_id
        // 1. If explicitly provided in request, use it (with validation)
        // 2. If user belongs to an organization, use their organization
        // 3. Otherwise, leave as null (for private caregivers or clients)
        if (!isset($data['organization_id']) || $data['organization_id'] === null) {
            if ($user->organization_id) {
                $data['organization_id'] = $user->organization_id;
            }
        } else {
            // If organization_id is provided, validate user has access to it
            // Only allow setting organization_id if user belongs to that organization
            // or is the owner of that organization
            if ($user->organization_id !== $data['organization_id']) {
                return response()->json([
                    'message' => 'Вы не можете создавать пациентов для другой организации',
                ], 403);
            }
        }

        $patient = Patient::create($data);

        return response()->json($patient, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/patients/{id}",
     *     tags={"Patients"},
     *     summary="Get a specific patient",
     *     description="Retrieve details of a specific patient. Access is restricted to the creator or organization members.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Patient ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Patient details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="creator_id", type="integer", example=1),
     *             @OA\Property(property="organization_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="first_name", type="string", example="Ivan"),
     *             @OA\Property(property="last_name", type="string", example="Petrov"),
     *             @OA\Property(property="middle_name", type="string", nullable=true, example="Sergeevich"),
     *             @OA\Property(property="birth_date", type="string", format="date", nullable=true, example="1950-05-15"),
     *             @OA\Property(property="gender", type="string", example="male"),
     *             @OA\Property(property="weight", type="integer", nullable=true, example=75),
     *             @OA\Property(property="height", type="integer", nullable=true, example=175),
     *             @OA\Property(property="mobility", type="string", example="walking"),
     *             @OA\Property(property="diagnoses", type="array", nullable=true, @OA\Items(type="string"), example={"Stroke", "Dementia"}),
     *             @OA\Property(property="needed_services", type="array", nullable=true, @OA\Items(type="string"), example={"Nursing care", "Physical therapy"}),
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
     *     )
     * )
     */
    public function show(Request $request, Patient $patient): JsonResponse
    {
        $user = $request->user();

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'You do not have access to this patient.',
            ], 403);
        }

        return response()->json($patient, 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/patients/{id}",
     *     tags={"Patients"},
     *     summary="Update a patient",
     *     description="Update patient information. Access is restricted to the creator or organization members.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Patient ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", example="Ivan"),
     *             @OA\Property(property="last_name", type="string", example="Petrov"),
     *             @OA\Property(property="middle_name", type="string", nullable=true, example="Sergeevich"),
     *             @OA\Property(property="birth_date", type="string", format="date", nullable=true, example="1950-05-15"),
     *             @OA\Property(property="gender", type="string", example="male", enum={"male", "female"}),
     *             @OA\Property(property="weight", type="integer", nullable=true, example=75),
     *             @OA\Property(property="height", type="integer", nullable=true, example=175),
     *             @OA\Property(property="mobility", type="string", example="walking", enum={"walking", "sitting", "bedridden"}),

     *             @OA\Property(property="diagnoses", type="array", nullable=true, @OA\Items(type="string"), example={"Stroke", "Dementia"}),
     *             @OA\Property(property="needed_services", type="array", nullable=true, @OA\Items(type="string"), example={"Nursing care", "Physical therapy"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Patient updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="creator_id", type="integer", example=1),
     *             @OA\Property(property="organization_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="first_name", type="string", example="Ivan"),
     *             @OA\Property(property="last_name", type="string", example="Petrov"),
     *             @OA\Property(property="middle_name", type="string", nullable=true, example="Sergeevich"),
     *             @OA\Property(property="birth_date", type="string", format="date", nullable=true, example="1950-05-15"),
     *             @OA\Property(property="gender", type="string", example="male"),
     *             @OA\Property(property="weight", type="integer", nullable=true, example=75),
     *             @OA\Property(property="height", type="integer", nullable=true, example=175),
     *             @OA\Property(property="mobility", type="string", example="walking"),
     *             @OA\Property(property="diagnoses", type="array", nullable=true, @OA\Items(type="string"), example={"Stroke", "Dementia"}),
     *             @OA\Property(property="needed_services", type="array", nullable=true, @OA\Items(type="string"), example={"Nursing care", "Physical therapy"}),
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
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $user = $request->user();

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'You do not have access to this patient.',
            ], 403);
        }

        $patient->update($request->validated());

        return response()->json($patient, 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/patients/{id}",
     *     tags={"Patients"},
     *     summary="Delete a patient",
     *     description="Delete a patient. Access is restricted to the creator or organization members.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Patient ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Patient deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Patient deleted successfully")
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
    public function destroy(Request $request, Patient $patient): JsonResponse
    {
        $user = $request->user();

        // Check access
        if (!$this->canAccessPatient($user, $patient)) {
            return response()->json([
                'message' => 'You do not have access to this patient.',
            ], 403);
        }

        $patient->delete();

        return response()->json([
            'message' => 'Patient deleted successfully',
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

            // Агентство: только назначенные пациенты
            if ($organization->isAgency()) {
                return $patient->assignedUsers()->where('user_id', $user->id)->exists();
            }
        }

        return false;
    }
}
