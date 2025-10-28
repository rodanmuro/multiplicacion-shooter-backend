<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\ShotController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Ruta de prueba
Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working!',
        'timestamp' => now()
    ]);
});

// Health check endpoint
Route::get('/health', function () {
    $checks = [
        'laravel' => true,
        'php_version' => PHP_VERSION,
        'database' => false,
        'storage_writable' => is_writable(storage_path('logs')),
        'cache_writable' => is_writable(storage_path('framework/cache')),
        'env_loaded' => config('app.key') !== null,
        'app_key_set' => !empty(config('app.key')),
    ];

    // Verificar conexión a base de datos
    try {
        DB::connection()->getPdo();
        $checks['database'] = true;
        $checks['database_name'] = DB::connection()->getDatabaseName();

        // Verificar que las tablas principales existen
        $checks['tables'] = [
            'users' => Schema::hasTable('users'),
            'game_sessions' => Schema::hasTable('game_sessions'),
            'shots' => Schema::hasTable('shots'),
        ];
    } catch (\Exception $e) {
        $checks['database_error'] = $e->getMessage();
    }

    // Determinar status general
    $allHealthy = $checks['laravel']
        && $checks['database']
        && $checks['storage_writable']
        && $checks['cache_writable']
        && $checks['env_loaded']
        && $checks['app_key_set'];

    return response()->json([
        'status' => $allHealthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now(),
        'environment' => config('app.env'),
    ], $allHealthy ? 200 : 500);
});

// Rutas de autenticación (públicas)
Route::post('/auth/verify', [AuthController::class, 'verify']);

// Rutas protegidas con autenticación Google
Route::middleware('auth.google')->group(function () {
    // Sesiones de juego
    Route::get('/sessions', [GameSessionController::class, 'index']);
    Route::get('/sessions/{id}', [GameSessionController::class, 'show']);
    Route::post('/sessions', [GameSessionController::class, 'store']);
    Route::put('/sessions/{id}/finish', [GameSessionController::class, 'finish']);

    // Disparos dentro de una sesión
    Route::post('/sessions/{id}/shots', [ShotController::class, 'store']);
});

// Rutas de administración (requiere auth.google + require.admin)
Route::middleware(['auth.google', 'require.admin'])->prefix('admin')->group(function () {
    // Gestión de usuarios
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/users/{userId}/sessions', [AdminController::class, 'getUserSessions']);
    Route::post('/users/upload-csv', [AdminController::class, 'uploadCsv']);
});
