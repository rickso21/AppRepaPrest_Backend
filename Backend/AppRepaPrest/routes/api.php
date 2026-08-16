<?php

use App\Http\Controllers\mapa\MapaController;
use App\Http\Controllers\prestamo\prestamoController;
use App\Http\Controllers\admin\adminController;
use App\Http\Controllers\pago\pagoController;
use App\Http\Controllers\MercadoPagoAllExterno\MercadoPagoController;
use App\Http\Controllers\tranferencia\tranferenciaController;
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
    // 2. Generar opciones de préstamo y crear solicitud
    Route::post('/genera-opciones', [prestamoController::class, 'genera_opciones']);




    // Abonar Pagos
    Route::post('/registrar-pago', [pagoController::class, 'registrarPago']);


    Route::get('/consultar-estado-cuenta-user', [prestamoController::class, 'consultarEstadoCuenta']);


    // Registrar pago con transferencia
    Route::post('/registrar-pago-transferencia', [tranferenciaController::class, 'registrarPagoTransferencia']);

    // Registrar pago con transferencia
    Route::post('/registrar-pago-transferencia', [tranferenciaController::class, 'registrarPagoTransferencia']);

    // Verificar transferencia (subir comprobante)
    Route::post('/verificar-transferencia', [tranferenciaController::class, 'verificarTransferencia']);

    // Validar transferencia manualmente (Asesor)
    Route::post('/validar-transferencia-manual', [tranferenciaController::class, 'validarTransferenciaManual']);

    // Verificar transferencias pendientes (Cron/Manual)
    Route::get('/verificar-transferencias-pendientes', [tranferenciaController::class, 'verificarTransferenciasPendientes']);

    // Consultar estado de una transferencia
    Route::get('/consultar-estado-transferencia', [tranferenciaController::class, 'consultarEstadoTransferencia']);



    // 1. Consultar estado de cuenta de un cliente con detalles especificos de su prestamo
    Route::get('/consultar-estado-cuenta', [adminController::class, 'consultarEstadoCuenta']);
    // 2. Administra solicitud de prestamos (aprobar, rechazar y liquidar)
    Route::put('/prestamo/aprobar/{id}', [adminController::class, 'aprobar_prestamo']);
    // 3. Listar todos los préstamos
    //Route::get('/listar-prestamos', [adminController::class, 'listarPrestamos']);
    Route::get('/prestamo/show', [adminController::class, 've_prestamos']);

    // 3. Genera pdf usuarios (Solicitud de Prestamos)
    Route::get('/prestamos/{id}/pdf/{tipo}', [adminController::class, 'descargarPdfPrestamo'])
            ->where('tipo', 'aprobacion|liquidacion|rechazo');

});


//Mercado Pago Validacion de Usuario Externo

//1 * Consultar información de un pago en Mercado Pago
Route::get('/consultar-pago-mp', [MercadoPagoController::class, 'consultarPagoMercadoPago']);
/*
GET /api/consultar-pago-mp?pago_id=123
GET /api/consultar-pago-mp?preference_id=MP-123456
GET /api/consultar-pago-mp?payment_id=1234567890
*/
//2 * Consultar preferencia de Mercado Pago
Route::get('/consultar-preferencia-mp', [MercadoPagoController::class, 'consultarPreferenciaMercadoPago']);
/*
GET /api/consultar-preferencia-mp?preference_id=MP-123456
*/


Route::post('/login', [loginController::class, 'login']);
Route::post('/register', [loginController::class, 'register']);
Route::post('/register_admin', [loginController::class, 'register_admin']);
Route::post('/forgot-password', [passwordController::class, 'olvide_password']);
Route::post('/new-password', [passwordController::class, 'nueva_password']);
Route::post('/verificar_pago', [pagoController::class, 'verificarPago']);
Route::post('/admin/register', [adminController::class, 'index']);
