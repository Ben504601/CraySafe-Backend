<?php

//require_once __DIR__ . '/api.php';

use Illuminate\Support\Facades\Route;

// Route::get('/api/test', function () {
//     return response()->json(['message' => 'Test from web.php']);
// });
// not reading api.php, fixed -bengie

Route::get('/', function () {
    return view('welcome');
});
