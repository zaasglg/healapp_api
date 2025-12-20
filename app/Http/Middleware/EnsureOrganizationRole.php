<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Allowed roles (owner, admin, doctor, caregiver)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Требуется авторизация');
        }

        // Получаем организацию из route parameter
        $organization = $request->route('organization');

        // Если передан ID, загружаем модель
        if (is_numeric($organization)) {
            $organization = Organization::find($organization);
        }

        if (!$organization) {
            abort(404, 'Организация не найдена');
        }

        // Получаем роль пользователя в организации
        $userRole = $user->roleInOrganization($organization);

        if (!$userRole) {
            abort(403, 'Вы не являетесь сотрудником этой организации');
        }

        // Преобразуем строковые роли в enum
        $allowedRoles = array_map(
            fn($role) => OrganizationRole::tryFrom($role),
            $roles
        );

        // Проверяем, есть ли роль пользователя в разрешённых
        if (!in_array($userRole, $allowedRoles, true)) {
            abort(403, 'Недостаточно прав для выполнения этого действия');
        }

        return $next($request);
    }
}
