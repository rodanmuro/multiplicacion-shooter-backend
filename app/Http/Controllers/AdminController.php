<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * Controlador de administración
 * Maneja operaciones administrativas: listar usuarios, ver sesiones, cargar CSV
 */
class AdminController extends Controller
{
    /**
     * Obtiene la lista de grupos únicos en el sistema
     *
     * GET /api/admin/groups
     *
     * @return JsonResponse
     */
    public function getGroups(): JsonResponse
    {
        try {
            $groups = User::whereNotNull('group')
                ->where('group', '!=', '')
                ->distinct()
                ->orderBy('group')
                ->pluck('group');

            return response()->json([
                'success' => true,
                'data' => $groups
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener grupos',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista todos los usuarios del sistema con paginación y filtros
     *
     * GET /api/admin/users
     *
     * Query params:
     * - group: Filtrar por grupo
     * - profile: Filtrar por perfil (student, teacher, admin)
     * - search: Buscar por nombre o email
     * - per_page: Resultados por página (default 40)
     * - sort_by: Campo por el cual ordenar (email, name, group, sessions_count, created_at)
     * - order: Orden ascendente o descendente (asc, desc)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listUsers(Request $request): JsonResponse
    {
        try {
            $query = User::query();

            // Filtro por grupo
            if ($request->filled('group')) {
                $query->where('group', $request->input('group'));
            }

            // Filtro por perfil
            if ($request->filled('profile')) {
                $query->where('profile', $request->input('profile'));
            }

            // Búsqueda por nombre o email
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Configurar ordenamiento
            $sortBy = $request->input('sort_by', 'created_at');
            $order = $request->input('order', 'desc');

            // Validar dirección de orden
            if (!in_array($order, ['asc', 'desc'])) {
                $order = 'desc';
            }

            // Mapeo de campos permitidos para ordenamiento
            $allowedSortFields = [
                'email' => 'email',
                'name' => 'name',
                'group' => 'group',
                'created_at' => 'created_at',
                'sessions_count' => 'game_sessions_count'
            ];

            // Obtener usuarios con conteo de sesiones
            $perPage = $request->input('per_page', 40);
            $query->withCount('gameSessions');

            // Aplicar ordenamiento
            if (isset($allowedSortFields[$sortBy])) {
                $dbField = $allowedSortFields[$sortBy];
                $query->orderBy($dbField, $order);
            } else {
                // Default: ordenar por fecha de creación
                $query->orderBy('created_at', 'desc');
            }

            $users = $query->paginate($perPage);

            // Agregar estadísticas por usuario
            $usersWithStats = collect($users->items())->map(function ($user) {
                // Obtener estadísticas de sesiones
                $sessions = GameSession::where('user_id', $user->id)
                    ->whereNotNull('finished_at')
                    ->get();

                $avgScore = $sessions->avg('final_score');
                $bestScore = $sessions->max('final_score');
                $lastSession = $sessions->sortByDesc('started_at')->first();

                return array_merge($user->toArray(), [
                    'avg_score' => $avgScore ? round($avgScore, 1) : null,
                    'best_score' => $bestScore,
                    'last_played_at' => $lastSession ? $lastSession->started_at : null,
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => $usersWithStats,
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem()
                ],
                'filters_applied' => [
                    'group' => $request->input('group'),
                    'profile' => $request->input('profile'),
                    'search' => $request->input('search'),
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
     * Obtiene las sesiones de un usuario específico con filtros
     *
     * GET /api/admin/users/{userId}/sessions
     *
     * Query params:
     * - date_from: Fecha inicio (YYYY-MM-DD)
     * - date_to: Fecha fin (YYYY-MM-DD)
     * - per_page: Resultados por página (default 10)
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

            // Construir query con filtros de fecha
            $query = GameSession::where('user_id', $userId)->with('shots');

            // Filtro fecha desde
            if ($request->filled('date_from')) {
                $query->whereDate('started_at', '>=', $request->input('date_from'));
            }

            // Filtro fecha hasta
            if ($request->filled('date_to')) {
                $query->whereDate('started_at', '<=', $request->input('date_to'));
            }

            // Obtener sesiones con paginación
            $perPage = $request->input('per_page', 10);
            $sessions = $query->orderBy('started_at', 'desc')->paginate($perPage);

            // Calcular estadísticas globales del usuario (con filtros aplicados)
            $allFilteredSessions = (clone $query)->whereNotNull('finished_at')->get();
            $summary = [
                'total_sessions' => $allFilteredSessions->count(),
                'avg_score' => $allFilteredSessions->avg('final_score') ? round($allFilteredSessions->avg('final_score'), 1) : 0,
                'best_score' => $allFilteredSessions->max('final_score') ?? 0,
                'total_playtime_minutes' => round($allFilteredSessions->sum('duration_seconds') / 60, 1),
                'first_session' => $allFilteredSessions->min('started_at'),
                'last_session' => $allFilteredSessions->max('started_at'),
            ];

            // Agregar estadísticas calculadas a cada sesión
            $sessionsWithStats = $sessions->getCollection()->map(function ($session, $index) use ($sessions) {
                $totalShots = $session->shots->count();
                $correctShots = $session->shots->where('is_correct', true)->count();
                $wrongShots = $totalShots - $correctShots;
                $accuracy = $totalShots > 0 ? round(($correctShots / $totalShots) * 100, 2) : 0;

                // Calcular número de fila global
                $rowNumber = (($sessions->currentPage() - 1) * $sessions->perPage()) + $index + 1;

                return [
                    'row_number' => $rowNumber,
                    'id' => $session->id,
                    'user_id' => $session->user_id,
                    'group_snapshot' => $session->group_snapshot,
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
                'summary' => $summary,
                'data' => $sessionsWithStats,
                'pagination' => [
                    'total' => $sessions->total(),
                    'per_page' => $sessions->perPage(),
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'from' => $sessions->firstItem(),
                    'to' => $sessions->lastItem()
                ],
                'filters_applied' => [
                    'date_from' => $request->input('date_from'),
                    'date_to' => $request->input('date_to'),
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

    /**
     * Exporta la lista de usuarios a CSV
     *
     * GET /api/admin/export/users
     *
     * Query params: mismos filtros que listUsers
     *
     * @param Request $request
     * @return Response
     */
    public function exportUsers(Request $request): Response
    {
        try {
            $query = User::query();

            // Aplicar filtros (igual que listUsers)
            if ($request->filled('group')) {
                $query->where('group', $request->input('group'));
            }

            if ($request->filled('profile')) {
                $query->where('profile', $request->input('profile'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->withCount('gameSessions')
                ->orderBy('created_at', 'desc')
                ->get();

            // Generar CSV
            $csvContent = "Email,Nombre,Apellido,Perfil,Grupo,Sesiones,Promedio,Mejor Puntuacion,Ultima Sesion,Fecha Registro\n";

            foreach ($users as $user) {
                $sessions = GameSession::where('user_id', $user->id)
                    ->whereNotNull('finished_at')
                    ->get();

                $avgScore = $sessions->avg('final_score') ? round($sessions->avg('final_score'), 1) : 0;
                $bestScore = $sessions->max('final_score') ?? 0;
                $lastSession = $sessions->sortByDesc('started_at')->first();
                $lastPlayedAt = $lastSession ? date('Y-m-d H:i', strtotime($lastSession->started_at)) : '';

                $csvContent .= sprintf(
                    "%s,%s,%s,%s,%s,%d,%.1f,%d,%s,%s\n",
                    $user->email,
                    $user->name ?? '',
                    $user->lastname ?? '',
                    $user->profile,
                    $user->group ?? '',
                    $user->game_sessions_count,
                    $avgScore,
                    $bestScore,
                    $lastPlayedAt,
                    date('Y-m-d', strtotime($user->created_at))
                );
            }

            $filename = 'usuarios_' . date('Y-m-d_H-i-s') . '.csv';

            return response($csvContent, 200)
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");

        } catch (Exception $e) {
            return response("Error al exportar: " . $e->getMessage(), 500);
        }
    }

    /**
     * Exporta las sesiones de un usuario a CSV
     *
     * GET /api/admin/export/users/{userId}/sessions
     *
     * Query params: date_from, date_to
     *
     * @param Request $request
     * @param int $userId
     * @return Response
     */
    public function exportUserSessions(Request $request, int $userId): Response
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return response("Usuario no encontrado", 404);
            }

            $query = GameSession::where('user_id', $userId)->with('shots');

            // Aplicar filtros de fecha
            if ($request->filled('date_from')) {
                $query->whereDate('started_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('started_at', '<=', $request->input('date_to'));
            }

            $sessions = $query->orderBy('started_at', 'desc')->get();

            // Generar CSV
            $userName = trim(($user->name ?? '') . ' ' . ($user->lastname ?? ''));
            $csvContent = "# Sesiones de: {$userName} ({$user->email})\n";
            $csvContent .= "# Grupo: " . ($user->group ?? 'N/A') . "\n";
            $csvContent .= "# Exportado: " . date('Y-m-d H:i:s') . "\n";
            $csvContent .= "#\n";
            $csvContent .= "Numero,Fecha,Hora,Puntuacion,Nivel Maximo,Disparos Totales,Aciertos,Errores,Precision,Duracion (seg)\n";

            $rowNum = 1;
            foreach ($sessions as $session) {
                $totalShots = $session->shots->count();
                $correctShots = $session->shots->where('is_correct', true)->count();
                $wrongShots = $totalShots - $correctShots;
                $accuracy = $totalShots > 0 ? round(($correctShots / $totalShots) * 100, 2) : 0;

                $csvContent .= sprintf(
                    "%d,%s,%s,%d,%d,%d,%d,%d,%.2f%%,%d\n",
                    $rowNum,
                    date('Y-m-d', strtotime($session->started_at)),
                    date('H:i:s', strtotime($session->started_at)),
                    $session->final_score,
                    $session->max_level_reached,
                    $totalShots,
                    $correctShots,
                    $wrongShots,
                    $accuracy,
                    $session->duration_seconds
                );
                $rowNum++;
            }

            $filename = 'sesiones_' . preg_replace('/[^a-zA-Z0-9]/', '_', $user->email) . '_' . date('Y-m-d') . '.csv';

            return response($csvContent, 200)
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");

        } catch (Exception $e) {
            return response("Error al exportar: " . $e->getMessage(), 500);
        }
    }
}
