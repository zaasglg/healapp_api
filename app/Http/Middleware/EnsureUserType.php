<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$types  Allowed user types (organization, private_caregiver, client)
     */
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Требуется авторизация');
        }

        $userType = $user->type?->value;

        if (!$userType || !in_array($userType, $types)) {
            abort(403, 'Этот функционал недоступен для вашего типа аккаунта');
        }

        return $next($request);
    }
}
