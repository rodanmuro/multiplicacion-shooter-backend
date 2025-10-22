<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\Shot;
use App\Models\User;
use App\Http\Requests\FinishSessionRequest;
use App\Http\Resources\GameSessionResource;
use App\Http\Resources\ShotResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador GameSessionController
 * Maneja las operaciones de sesiones de juego
 */
class GameSessionController extends Controller
{
    /**
     * Obtener listado de sesiones del usuario autenticado
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Obtener usuario autenticado del middleware
        $user = $request->input('authenticated_user');

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado'
            ], 401);
        }

        // Obtener sesiones del usuario, ordenadas por fecha de inicio descendente
        // Paginadas (10 por página) y con eager loading de shots para las estadísticas
        $sessions = GameSession::where('user_id', $user->id)
            ->with('shots')
            ->orderBy('started_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => GameSessionResource::collection($sessions->items()),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ]
        ], 200);
    }

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

    /**
     * Finalizar una sesión de juego
     *
     * @param FinishSessionRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function finish(FinishSessionRequest $request, int $id): JsonResponse
    {
        // Usuario autenticado provisto por el middleware
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

        // Validar pertenencia
        if ($session->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Acceso prohibido a la sesión especificada'
            ], 403);
        }

        // Validar que esté activa
        if (!$session->isActive()) {
            return response()->json([
                'success' => false,
                'error' => 'La sesión ya ha finalizado'
            ], 400);
        }

        // Actualizar datos finales
        $data = $request->validated();

        $session->finished_at = $data['finished_at'];
        $session->final_score = $data['final_score'];
        $session->max_level_reached = $data['max_level_reached'];
        $session->duration_seconds = $data['duration_seconds'];
        $session->save();

        // Calcular estadísticas basadas en shots
        $totalShots = Shot::where('game_session_id', $session->id)->count();
        $correctShots = Shot::where('game_session_id', $session->id)->where('is_correct', true)->count();
        $wrongShots = $totalShots - $correctShots;
        $accuracy = $totalShots > 0 ? round(($correctShots * 100.0) / $totalShots, 2) : 0.0;

        $responseData = $session->toArray();
        $responseData['total_shots'] = $totalShots;
        $responseData['correct_shots'] = $correctShots;
        $responseData['wrong_shots'] = $wrongShots;
        $responseData['accuracy'] = $accuracy;

        return response()->json([
            'success' => true,
            'data' => $responseData
        ], 200);
    }

    /**
     * Obtener detalle de una sesión específica con sus disparos
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        // Obtener usuario autenticado del middleware
        $user = $request->input('authenticated_user');

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no autenticado'
            ], 401);
        }

        // Buscar sesión con eager loading de shots
        $session = GameSession::with('shots')->find($id);

        if (!$session) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión no encontrada'
            ], 404);
        }

        // Validar que la sesión pertenece al usuario autenticado
        if ($session->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Acceso prohibido a la sesión especificada'
            ], 404); // 404 en lugar de 403 para no revelar existencia de la sesión
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session' => new GameSessionResource($session),
                'shots' => ShotResource::collection($session->shots)
            ]
        ], 200);
    }
}
