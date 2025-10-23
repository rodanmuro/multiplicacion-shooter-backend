<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

/**
 * Middleware para verificar que el usuario autenticado sea administrador
 */
class RequireAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obtener el usuario desde el request (establecido por auth.google middleware)
        $user = $request->input('authenticated_user');

        // Verificar que el usuario exista y sea admin
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Acceso denegado. Se requieren permisos de administrador.'
            ], 403);
        }

        return $next($request);
    }
}
