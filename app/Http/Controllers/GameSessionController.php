<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador GameSessionController
 * Maneja las operaciones de sesiones de juego
 */
class GameSessionController extends Controller
{
    /**
     * Crear una nueva sesión de juego
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Validar datos de entrada
        $validated = $request->validate([
            'started_at' => 'required|date',
            'canvas_width' => 'required|integer|min:1|max:10000',
            'canvas_height' => 'required|integer|min:1|max:10000',
        ]);

        // Obtener usuario autenticado del middleware
        $user = $request->input('authenticated_user');

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado'
            ], 401);
        }

        // Crear sesión de juego
        $session = GameSession::create([
            'user_id' => $user->id,
            'started_at' => $validated['started_at'],
            'final_score' => 0,
            'max_level_reached' => 1,
            'duration_seconds' => 0,
            'canvas_width' => $validated['canvas_width'],
            'canvas_height' => $validated['canvas_height']
        ]);

        return response()->json([
            'success' => true,
            'data' => $session
        ], 201);
    }
}
