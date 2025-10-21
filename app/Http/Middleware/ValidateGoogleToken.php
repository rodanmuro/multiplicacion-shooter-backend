<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\GoogleAuthService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware ValidateGoogleToken
 * Valida el token de Google OAuth en el header Authorization
 * y agrega el usuario autenticado al request
 */
class ValidateGoogleToken
{
    protected GoogleAuthService $googleAuthService;

    public function __construct(GoogleAuthService $googleAuthService)
    {
        $this->googleAuthService = $googleAuthService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extraer token del header Authorization
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'error' => 'Token no proporcionado',
                'message' => 'Se requiere un token de autenticación en el header Authorization'
            ], 401);
        }

        $token = substr($authHeader, 7); // Remover "Bearer "

        try {
            // Verificar token con Google
            $googleUser = $this->googleAuthService->verifyToken($token);

            // Buscar usuario en base de datos
            $user = User::where('google_id', $googleUser['sub'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado',
                    'message' => 'El usuario no está registrado en el sistema'
                ], 401);
            }

            // Agregar usuario autenticado al request
            $request->merge(['authenticated_user' => $user]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Token inválido o expirado',
                'message' => $e->getMessage()
            ], 401);
        }

        return $next($request);
    }
}
