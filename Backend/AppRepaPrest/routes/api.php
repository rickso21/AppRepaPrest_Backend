<?php

use App\Http\Controllers\comunidad\commentController;
use App\Http\Controllers\comunidad\publicacionController;
use App\Http\Controllers\mapa\MapaController;
use App\Http\Controllers\prestamo\prestamoController;
use App\Http\Controllers\user\adminController;
use App\Http\Controllers\user\loginController;
use App\Http\Controllers\user\passwordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')->group(function () {
    // 1. Actualizar ubicación (cada 3-5 segundos)
    Route::post('/mapa/ubicacion', [MapaController::class, 'Current_location']);
    Route::get('/mapa/repartidores', [MapaController::class, 'General_location']);
    Route::get('/mapa/repartidores/{id}', [MapaController::class, 'Specific_user']);
    Route::post('/mapa/estado', [MapaController::class, 'UserStatus']);

    // 4. Activar/Desactivar pánico
    Route::post('/mapa/panico', [MapaController::class, 'On_User_alert']);

    // Actualizar datos del usuario
    Route::put('/update', [loginController::class, 'edit_user']);

    // solicita prestamo
    Route::get('/prestamo/solicita', [prestamoController::class, 'solicita_user']);
    Route::get('/prestamo/show', [adminController::class, 've_prestamos']);
    Route::put('/prestamo/aprobar/{id}', [adminController::class, 'aprobar_prestamo']);

    // comunidad
    Route::prefix('publication')->group(function () {
        Route::get('/', [publicacionController::class, 'index']);
        Route::post('/save', [publicacionController::class, 'store']);
        Route::delete('/delete/{id}', [publicacionController::class, 'destroy']);
        Route::post('/comment/{post}', [commentController::class, 'store']);
        Route::put('/update/{id}', [publicacionController::class, 'update']); // actualizar publicación
        Route::delete('/comment/{id}', [commentController::class, 'destroy']); // eliminar comentario
        Route::put('/comment/{id}', [commentController::class, 'update']); // actualizar comentario
    });

});

Route::post('/login', [loginController::class, 'login']);
Route::post('/register', [loginController::class, 'register']);
Route::post('/register_admin', [loginController::class, 'register_admin']);
Route::post('/forgot-password', [passwordController::class, 'olvide_password']);
Route::post('/new-password', [passwordController::class, 'nueva_password']);
Route::post('/admin/register', [adminController::class, 'index']);


