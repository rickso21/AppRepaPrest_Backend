<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

use App\Http\Controllers\user\loginController;

use App\Http\Controllers\mapa\MapaController;


Route::middleware('auth:sanctum')->group(function () {
    // 1. Actualizar ubicación (cada 3-5 segundos)
    Route::post('/mapa/ubicacion', [MapaController::class, 'Current_location']);
    Route::get('/mapa/repartidores', [MapaController::class, 'General_location']);
    Route::get('/mapa/repartidores/{id}', [MapaController::class, 'Specific_user']);
    Route::post('/mapa/estado', [MapaController::class, 'UserStatus']);

    // 4. Activar/Desactivar pánico
    Route::post('/mapa/panico', [MapaController::class, 'On_User_alert']);
});

Route::post('/login', [loginController::class, 'login']);
Route::post('/register', [loginController::class, 'register']);
Route::post('/register_admin', [loginController::class, 'register_admin']);
Route::post('/forgot-password', [passwordController::class, 'olvide_password']);
Route::post('/new-password', [passwordController::class, 'nueva_password']);


