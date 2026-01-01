<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminTokenAuth
{
    /**
     * Проверка токена админки из URL параметра
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('token') ?? $request->session()->get('admin_token');
        $validToken = config('app.admin_token');

        if (!$token || !$validToken || $token !== $validToken) {
            abort(403, 'Доступ запрещён');
        }

        // Сохраняем токен в сессию для последующих запросов
        $request->session()->put('admin_token', $token);

        return $next($request);
    }
}
