<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLogin;
use App\Services\GoogleAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Controlador de autenticación
 * Maneja el login con Google OAuth
 */
class AuthController extends Controller
{
    private GoogleAuthService $googleAuthService;

    public function __construct(GoogleAuthService $googleAuthService)
    {
        $this->googleAuthService = $googleAuthService;
    }

    /**
     * Verifica el token de Google y autentica al usuario
     *
     * POST /api/auth/verify
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        // Validar que el token esté presente
        $request->validate([
            'token' => 'required|string'
        ]);

        try {
            // 1. Verificar el token con Google
            $googleUser = $this->googleAuthService->verifyToken($request->token);

            // 2. Buscar o crear usuario en la base de datos
            $user = User::firstOrCreate(
                ['google_id' => $googleUser['sub']], // Buscar por google_id
                [
                    'email' => $googleUser['email'],
                    'name' => $googleUser['name'],
                    'picture' => $googleUser['picture'],
                    'profile' => User::PROFILE_STUDENT // Por defecto: student
                ]
            );

            // 3. SIEMPRE registrar el login (aunque el usuario ya exista)
            UserLogin::create([
                'user_id' => $user->id,
                'logged_in_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // 4. Retornar datos del usuario
            return response()->json([
                'success' => true,
                'data' => $user
            ], 200);

        } catch (Exception $e) {
            // Manejo de errores
            return response()->json([
                'success' => false,
                'error' => 'Error al verificar token de Google',
                'message' => $e->getMessage()
            ], 401);
        }
    }
}
