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
    Route::post('/mapa/ubicacion', [MapaController::class, 'actualizarUbicacion']);

    // 2. Obtener ubicaciones de todos los repartidores activos
    Route::get('/mapa/repartidores', [MapaController::class, 'obtenerRepartidores']);

    // 3. Cambiar estado (conectar/desconectar)
    Route::post('/mapa/estado', [MapaController::class, 'cambiarEstado']);

    // 4. Activar/Desactivar pánico
    Route::post('/mapa/panico', [MapaController::class, 'togglePanico']);
});

Route::post('/login', [loginController::class, 'login']);
Route::post('/register', [loginController::class, 'register']);



