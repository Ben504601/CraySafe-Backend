<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\DB;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/register', [AuthController::class, 'register']);

Route::get('/dashboard', [AuthController::class, 'dashboard']);

Route::get('/tank/{id}', [AuthController::class, 'tankDetail']);

Route::post('/tank/{id}/mode', [AuthController::class, 'switchMode']);

Route::get('/tank/{id}/time-to-danger', [AuthController::class, 'timeToDanger']);

Route::get('/tank/{id}/reports', [AuthController::class, 'getTankReports']);

Route::get('/tank/{id}/reports/pdf', [AuthController::class, 'downloadReportPdf']);

Route::get('/alerts', [AuthController::class, 'getAlerts']);

Route::get('/alerts/unread-count', [AuthController::class, 'getUnreadAlertCount']);

Route::post('/alerts/{id}/read', [AuthController::class, 'markAlertRead']);

Route::post('/sensor-data', [AuthController::class, 'postSensorData']);

Route::post('/fcm-token', [AuthController::class, 'saveFcmToken']);

Route::get('/support/qa', [AuthController::class, 'getDiagnosticQnA']);

// TEST ROUTE
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working!',
        'time' => now()->toDateTimeString()
    ]);
});

Route::get('/db-test', function() {
    try {
        DB::connection()->getPdo();
        return 'DB OK: ' . DB::connection()->getDatabaseName();
    } catch (\Throwable $e) {
        return get_class($e) . ': ' . $e->getMessage();
    }
});

Route::get('/tcp-test', function () {
    $host = env('DB_HOST');
    $port = (int) env('DB_PORT', 3306);
    $fp = @fsockopen($host, $port, $errno, $errstr, 5);
    return $fp ? 'TCP OK' : "TCP FAIL: $errno $errstr";
});

Route::get('/pdo-test', function () {
    try {
        $dsn = "mysql:host=" . env('DB_HOST') . ";port=" . env('DB_PORT') . ";dbname=" . env('DB_DATABASE');
        $pdo = new PDO($dsn, env('DB_USERNAME'), env('DB_PASSWORD'));
        return "PDO Connection Successful!";
    } catch (PDOException $e) {
        return "PDO Connection Failed: " . $e->getMessage();
    }
});

Route::get('/env-check', function () {
    return response()->json([
        'DB_HOST' => config('database.connections.mysql.host'),
        'DB_PORT' => config('database.connections.mysql.port'),
        'DB_DATABASE' => config('database.connections.mysql.database'),
        'DB_USERNAME' => config('database.connections.mysql.username'),
        'DB_PASSWORD_SET' => !empty(config('database.connections.mysql.password')),
    ]);
});

Route::get('/env-raw', function () {
    return response()->json([
        'DB_HOST' => env('DB_HOST'),
        'DB_PORT' => env('DB_PORT'),
        'DB_DATABASE' => env('DB_DATABASE'),
        'DB_USERNAME' => env('DB_USERNAME'),
        'DB_PASSWORD_SET' => !empty(env('DB_PASSWORD')),
    ]);
});

Route::post('/pair-tank', [AuthController::class, 'pairTank']);

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});