<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * Controlador de administración
 * Maneja operaciones administrativas: listar usuarios, ver sesiones, cargar CSV
 */
class AdminController extends Controller
{
    /**
     * Lista todos los usuarios del sistema con paginación
     *
     * GET /api/admin/users
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listUsers(Request $request): JsonResponse
    {
        try {
            // Obtener usuarios con conteo de sesiones
            $users = User::withCount('gameSessions')
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem()
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener usuarios',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene las sesiones de un usuario específico
     *
     * GET /api/admin/users/{userId}/sessions
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function getUserSessions(Request $request, int $userId): JsonResponse
    {
        try {
            // Verificar que el usuario exista
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            // Obtener sesiones del usuario con paginación
            $sessions = GameSession::where('user_id', $userId)
                ->with('shots')
                ->orderBy('started_at', 'desc')
                ->paginate(10);

            // Agregar estadísticas calculadas a cada sesión
            $sessionsWithStats = $sessions->getCollection()->map(function ($session) {
                $totalShots = $session->shots->count();
                $correctShots = $session->shots->where('is_correct', true)->count();
                $wrongShots = $totalShots - $correctShots;
                $accuracy = $totalShots > 0 ? round(($correctShots / $totalShots) * 100, 2) : 0;

                return [
                    'id' => $session->id,
                    'user_id' => $session->user_id,
                    'started_at' => $session->started_at,
                    'finished_at' => $session->finished_at,
                    'final_score' => $session->final_score,
                    'max_level_reached' => $session->max_level_reached,
                    'duration_seconds' => $session->duration_seconds,
                    'canvas_width' => $session->canvas_width,
                    'canvas_height' => $session->canvas_height,
                    'total_shots' => $totalShots,
                    'correct_shots' => $correctShots,
                    'wrong_shots' => $wrongShots,
                    'accuracy' => $accuracy,
                    'created_at' => $session->created_at,
                    'updated_at' => $session->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'lastname' => $user->lastname,
                    'profile' => $user->profile,
                    'group' => $user->group
                ],
                'data' => $sessionsWithStats,
                'pagination' => [
                    'total' => $sessions->total(),
                    'per_page' => $sessions->perPage(),
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage()
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener sesiones del usuario',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Carga masiva de usuarios mediante CSV
     *
     * POST /api/admin/users/upload-csv
     *
     * Formato CSV: email,group
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadCsv(Request $request): JsonResponse
    {
        // Validar que se recibió un archivo
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Archivo inválido',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $csvData = array_map('str_getcsv', file($file->getRealPath()));

            // Validar encabezados
            $headers = array_map('trim', $csvData[0]);
            if (!in_array('email', $headers) || !in_array('group', $headers)) {
                return response()->json([
                    'success' => false,
                    'error' => 'El CSV debe contener las columnas: email, group'
                ], 422);
            }

            // Procesar filas - columnas obligatorias y opcionales
            $emailIndex = array_search('email', $headers);
            $groupIndex = array_search('group', $headers);
            $nameIndex = array_search('name', $headers); // Opcional
            $lastnameIndex = array_search('lastname', $headers); // Opcional

            $created = 0;
            $updated = 0;
            $errors = [];

            // Saltar encabezados (empezar desde índice 1)
            for ($i = 1; $i < count($csvData); $i++) {
                $row = $csvData[$i];

                // Saltar filas vacías
                if (empty($row) || (count($row) == 1 && trim($row[0]) == '')) {
                    continue;
                }

                // Validar que la fila tenga suficientes columnas
                if (count($row) <= max($emailIndex, $groupIndex)) {
                    $errors[] = "Fila " . ($i + 1) . ": Formato inválido";
                    continue;
                }

                $email = trim($row[$emailIndex]);
                $group = trim($row[$groupIndex]);

                // Obtener name y lastname si existen en el CSV
                $name = ($nameIndex !== false && isset($row[$nameIndex]))
                    ? trim($row[$nameIndex])
                    : null;
                $lastname = ($lastnameIndex !== false && isset($row[$lastnameIndex]))
                    ? trim($row[$lastnameIndex])
                    : null;

                // Saltar si el email está vacío
                if (empty($email)) {
                    continue;
                }

                // Validar email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Fila " . ($i + 1) . ": Email inválido ({$email})";
                    continue;
                }

                // Buscar o crear usuario
                $user = User::where('email', $email)->first();

                if ($user) {
                    // Actualizar campos si el usuario ya existe
                    $updateData = ['group' => $group];

                    // Solo actualizar name/lastname si vienen en el CSV
                    if ($name !== null) {
                        $updateData['name'] = $name;
                    }
                    if ($lastname !== null) {
                        $updateData['lastname'] = $lastname;
                    }

                    $user->update($updateData);
                    $updated++;
                } else {
                    // Crear usuario pendiente (sin google_id, será completado al login)
                    User::create([
                        'email' => $email,
                        'group' => $group,
                        'profile' => User::PROFILE_STUDENT,
                        'google_id' => null, // Se asignará cuando haga login
                        'name' => $name,
                        'lastname' => $lastname,
                        'picture' => null
                    ]);
                    $created++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Carga completada',
                'stats' => [
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => count($errors)
                ],
                'error_details' => $errors
            ], 200);

        } catch (Exception $e) {
            \Log::error('Error en uploadCsv: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al procesar archivo CSV',
                'message' => $e->getMessage(),
                'debug' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }
}
