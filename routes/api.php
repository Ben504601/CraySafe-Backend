<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// LOGIN ROUTE
Route::post('/login', [AuthController::class, 'login']);

// REGISTRATION ROUTE
Route::post('/register', [AuthController::class, 'register']);

// DASHBOARD ROUTE
Route::get('/dashboard', [AuthController::class, 'dashboard']);

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