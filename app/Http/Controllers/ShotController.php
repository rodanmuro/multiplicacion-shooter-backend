<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordShotRequest;
use App\Models\GameSession;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Controlador para registrar disparos (shots)
 */
class ShotController extends Controller
{
    /**
     * Registra un disparo vinculado a una sesión de juego
     *
     * POST /api/sessions/{id}/shots
     *
     * @param RecordShotRequest $request
     * @param int $id  ID de la sesión de juego
     * @return JsonResponse
     */
    public function store(RecordShotRequest $request, int $id): JsonResponse
    {
        // Usuario autenticado inyectado por el middleware auth.google
        $user = $request->input('authenticated_user');

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado'
            ], 401);
        }

        // Buscar sesión
        $session = GameSession::find($id);
        if (!$session) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión no encontrada'
            ], 404);
        }

        // Validar pertenencia de la sesión al usuario autenticado
        if ($session->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Acceso prohibido a la sesión especificada'
            ], 403);
        }

        // Validar que la sesión esté activa
        if (!$session->isActive()) {
            return response()->json([
                'success' => false,
                'error' => 'La sesión ya ha finalizado'
            ], 400);
        }

        // Datos validados
        $data = $request->validated();

        // Crear shot
        $shot = Shot::create([
            'game_session_id' => $session->id,
            'shot_at' => $data['shot_at'],
            'coordinate_x' => $data['coordinate_x'],
            'coordinate_y' => $data['coordinate_y'],
            'factor_1' => $data['factor_1'],
            'factor_2' => $data['factor_2'],
            'correct_answer' => $data['correct_answer'],
            'card_value' => $data['card_value'],
            'is_correct' => $data['is_correct'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $shot
        ], 201);
    }
}

