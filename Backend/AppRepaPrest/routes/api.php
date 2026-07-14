<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

use App\Http\Controllers\user\loginController;


Route::post('/login', [loginController::class, 'login']);
Route::post('/register', [loginController::class, 'register']);



