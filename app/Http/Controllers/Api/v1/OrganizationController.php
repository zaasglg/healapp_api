<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Organization",
 *     description="API для управления организацией и сотрудниками"
 * )
 */
class OrganizationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/organization",
     *     tags={"Organization"},
     *     summary="Получить информацию об организации",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Organization"))
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $organization = $user->organization;

        if (!$organization) {
            return response()->json(['message' => 'Вы не принадлежите к организации'], 404);
        }

        $organization->load('owner');
        $organization->append(['employee_count', 'patient_count']);

        return response()->json($organization);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/organization/employees",
     *     tags={"Organization"},
     *     summary="Список сотрудников организации",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="role", in="query", @OA\Schema(type="string", enum={"owner", "admin", "doctor", "caregiver"})),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function getEmployees(Request $request): JsonResponse
    {
        $user = $request->user();
        $organization = $user->organization;

        if (!$organization) {
            return response()->json(['message' => 'Вы не принадлежите к организации'], 404);
        }

        $query = $organization->employees()->with('roles');

        if ($request->has('role')) {
            $query->role($request->role);
        }

        $employees = $query->get()->map(function ($employee) {
            return [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'middle_name' => $employee->middle_name,
                'phone' => $employee->phone,
                'role' => $employee->roles->first()?->name,
                'created_at' => $employee->created_at,
            ];
        });

        return response()->json($employees);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/organization/employees/{id}/role",
     *     tags={"Organization"},
     *     summary="Изменить роль сотрудника",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"role"},
     *             @OA\Property(property="role", type="string", enum={"admin", "doctor", "caregiver"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Роль изменена")
     * )
     */
    public function changeEmployeeRole(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|in:admin,doctor,caregiver',
        ]);

        $user = $request->user();

        // Только owner может менять роли
        if (!$user->isOwner()) {
            return response()->json(['message' => 'Только владелец может менять роли'], 403);
        }

        $organization = $user->organization;
        $employee = User::where('id', $id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'Сотрудник не найден'], 404);
        }

        // Нельзя изменить роль владельца
        if ($employee->isOwner()) {
            return response()->json(['message' => 'Нельзя изменить роль владельца'], 422);
        }

        $organization->changeEmployeeRole($employee, $request->role);

        return response()->json([
            'message' => 'Роль изменена',
            'employee' => [
                'id' => $employee->id,
                'role' => $request->role,
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/organization/employees/{id}",
     *     tags={"Organization"},
     *     summary="Удалить сотрудника из организации",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Сотрудник удалён")
     * )
     */
    public function removeEmployee(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->canManageEmployees()) {
            return response()->json(['message' => 'Недостаточно прав'], 403);
        }

        $organization = $user->organization;
        $employee = User::where('id', $id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'Сотрудник не найден'], 404);
        }

        // Нельзя удалить владельца
        if ($employee->isOwner()) {
            return response()->json(['message' => 'Нельзя удалить владельца'], 422);
        }

        // Admin не может удалить другого admin
        if (!$user->isOwner() && $employee->hasRole('admin')) {
            return response()->json(['message' => 'Только владелец может удалить администратора'], 403);
        }

        $organization->removeEmployee($employee);

        return response()->json(['message' => 'Сотрудник удалён из организации']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/organization/assign-diary-access",
     *     tags={"Organization"},
     *     summary="Назначить доступ к дневнику (для агентств)",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"patient_id", "user_id"},
     *             @OA\Property(property="patient_id", type="integer"),
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="permission", type="string", enum={"view", "edit", "full"}, default="edit")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Доступ назначен")
     * )
     */
    public function assignDiaryAccess(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'user_id' => 'required|exists:users,id',
            'permission' => 'nullable|string|in:view,edit,full',
        ]);

        $user = $request->user();

        if (!$user->canManageAccess()) {
            return response()->json(['message' => 'Недостаточно прав'], 403);
        }

        $organization = $user->organization;
        $patient = Patient::findOrFail($request->patient_id);
        $employee = User::findOrFail($request->user_id);

        // Проверки
        if ($patient->organization_id !== $organization->id) {
            return response()->json(['message' => 'Подопечный не принадлежит вашей организации'], 422);
        }

        if ($employee->organization_id !== $organization->id) {
            return response()->json(['message' => 'Сотрудник не принадлежит вашей организации'], 422);
        }

        if (!$patient->diary) {
            return response()->json(['message' => 'У подопечного нет дневника'], 422);
        }

        $permission = $request->permission ?? 'edit';
        $patient->diary->grantAccess($employee, $permission);

        return response()->json([
            'message' => 'Доступ к дневнику назначен',
            'patient_id' => $patient->id,
            'user_id' => $employee->id,
            'permission' => $permission,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/organization/revoke-diary-access",
     *     tags={"Organization"},
     *     summary="Отозвать доступ к дневнику",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"patient_id", "user_id"},
     *             @OA\Property(property="patient_id", type="integer"),
     *             @OA\Property(property="user_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Доступ отозван")
     * )
     */
    public function revokeDiaryAccess(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $user = $request->user();

        if (!$user->canManageAccess()) {
            return response()->json(['message' => 'Недостаточно прав'], 403);
        }

        $patient = Patient::findOrFail($request->patient_id);
        $employee = User::findOrFail($request->user_id);

        if ($patient->diary) {
            $patient->diary->revokeAccess($employee);
        }

        return response()->json(['message' => 'Доступ к дневнику отозван']);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/organization",
     *     tags={"Organization"},
     *     summary="Обновить информацию об организации",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Организация обновлена")
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Недостаточно прав'], 403);
        }

        $organization = $user->organization;

        if (!$organization) {
            return response()->json(['message' => 'Организация не найдена'], 404);
        }

        $organization->update($request->only(['name', 'address', 'phone', 'description']));

        return response()->json([
            'message' => 'Организация обновлена',
            'organization' => $organization,
        ]);
    }
}
