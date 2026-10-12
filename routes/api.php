<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\DB;

// ═══════════════════════════════════════════════════════════════
//  PUBLIC (unauthenticated) — tight per-IP limits
// ═══════════════════════════════════════════════════════════════

Route::post('/login',    [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');

// ═══════════════════════════════════════════════════════════════
//  AUTHENTICATED — specific per-user limits
// ═══════════════════════════════════════════════════════════════

Route::post('/sensor-data', [AuthController::class, 'postSensorData'])
    ->middleware('throttle:sensor-data');

Route::post('/fcm-token', [AuthController::class, 'saveFcmToken'])
    ->middleware('throttle:fcm-token');

// ═══════════════════════════════════════════════════════════════
//  AUTHENTICATED — general per-user bucket
// ═══════════════════════════════════════════════════════════════

Route::middleware('throttle:api-general')->group(function () {
    Route::get('/dashboard',                [AuthController::class, 'dashboard']);
    Route::get('/tank/{id}',                [AuthController::class, 'tankDetail']);
    Route::post('/tank/{id}/mode',          [AuthController::class, 'switchMode']);
    Route::get('/tank/{id}/time-to-danger', [AuthController::class, 'timeToDanger']);
    Route::get('/tank/{id}/reports',        [AuthController::class, 'getTankReports']);
    Route::get('/tank/{id}/reports/pdf',    [AuthController::class, 'downloadReportPdf']);
    Route::get('/alerts',                   [AuthController::class, 'getAlerts']);
    Route::get('/alerts/unread-count',      [AuthController::class, 'getUnreadAlertCount']);
    Route::post('/alerts/{id}/read',        [AuthController::class, 'markAlertRead']);
    Route::get('/support/qa',               [AuthController::class, 'getDiagnosticQnA']);
    Route::post('/pair-tank',               [AuthController::class, 'pairTank']);
});

// ═══════════════════════════════════════════════════════════════
//  WAKE PING — deliberately unthrottled so the Python bridge can
//  wake Render's free-tier cold start without a 429 in the way.
// ═══════════════════════════════════════════════════════════════

Route::get('/ping', fn () => response()->json(['message' => 'pong']));
Route::get('/test', fn () => response()->json([
    'success' => true,
    'message' => 'API is working!',
    'time' => now()->toDateTimeString(),
]));