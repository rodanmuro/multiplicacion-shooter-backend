<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\ShotController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

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
