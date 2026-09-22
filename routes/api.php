<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/register', [AuthController::class, 'register']);

Route::get('/dashboard', [AuthController::class, 'dashboard']);

Route::get('/tank/{id}', [AuthController::class, 'tankDetail']);

Route::post('/tank/{id}/mode', [AuthController::class, 'switchMode']);

Route::get('/tank/{id}/time-to-danger', [AuthController::class, 'timeToDanger']);

Route::get('/alerts', [AuthController::class, 'getAlerts']);

Route::post('/alerts/{id}/read', [AuthController::class, 'markAlertRead']);

Route::post('/sensor-data', [AuthController::class, 'postSensorData']);

// TEST ROUTE
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working!',
        'time' => now()->toDateTimeString()
    ]);
});

Route::post('/pair-tank', [AuthController::class, 'pairTank']);

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});