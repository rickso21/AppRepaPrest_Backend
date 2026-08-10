<?php

use App\Http\Controllers\mapa\MapaController;
use App\Http\Controllers\prestamo\prestamoController;
use App\Http\Controllers\AsesorPrestamo\AsesorController;

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
    Route::get('/solicita-user', [prestamoController::class, 'solicita_user']);


<<<<<<< HEAD
    // 2. Generar opciones de préstamo y crear solicitud
    Route::post('/genera-opciones', [prestamoController::class, 'genera_opciones']);



    



     
    // 1. Registrar pago de un cliente
    Route::post('/registrar-pago', [AsesorController::class, 'registrarPago']);
    
    // 2. Consultar estado de cuenta de un cliente
    Route::get('/consultar-estado-cuenta', [AsesorController::class, 'consultarEstadoCuenta']);
    
    // 3. Actualizar estado de un préstamo (aprobar, rechazar, etc.)
    Route::put('/actualizar-estado', [AsesorController::class, 'actualizarEstadoPrestamo']);
    
    // 4. Listar todos los préstamos (con filtros)
    Route::get('/listar-prestamos', [AsesorController::class, 'listarPrestamos']);

    Route::get('/prestamos/{id}/pdf/{tipo}', [AsesorController::class, 'descargarPdfPrestamo'])
            ->where('tipo', 'aprobacion|liquidacion|rechazo');
=======
    Route::post('/genera_opcione', [prestamoController::class, 'genera_opciones']);
>>>>>>> a7fab3927efcfd0ed808257fdc5dc261e453c3e8

});


Route::post('/login', [loginController::class, 'login']);
Route::post('/register', [loginController::class, 'register']);
Route::post('/register_admin', [loginController::class, 'register_admin']);
Route::post('/forgot-password', [passwordController::class, 'olvide_password']);
Route::post('/new-password', [passwordController::class, 'nueva_password']);
