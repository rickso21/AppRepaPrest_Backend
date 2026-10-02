<?php

use App\Http\Controllers\mapa\MapaController;
use App\Http\Controllers\LiveKitController;
use App\Http\Controllers\prestamo\prestamoController;
use App\Http\Controllers\admin\adminController;
use App\Http\Controllers\pago\pagoController;
use App\Http\Controllers\MercadoPagoAllExterno\MercadoPagoController;
use App\Http\Controllers\tranferencia\tranferenciaController;
use App\Http\Controllers\user\loginController;
use App\Http\Controllers\user\passwordController;
use App\Http\Controllers\comunidad\publicacionController;
use App\Http\Controllers\comunidad\commentController;
use App\Http\Controllers\comunidad\AdController;
use App\Http\Controllers\contacto\ContactoController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('ads')->group(function () {
        Route::get('/', [AdController::class, 'index']);
        Route::get('/my-ads', [AdController::class, 'myAds']);
        Route::post('/create', [AdController::class, 'create']);
        Route::post('/{id}/pay', [AdController::class, 'pay']);
        Route::post('/{id}/impression', [AdController::class, 'impression']);
        Route::post('/{id}/click', [AdController::class, 'click']);
    });

    // 1. Actualizar ubicación (cada 3-5 segundos)
    Route::post('/mapa/ubicacion', [MapaController::class, 'Current_location']);
    Route::get('/mapa/repartidores', [MapaController::class, 'General_location']);
    Route::get('/mapa/repartidores/{id}', [MapaController::class, 'Specific_user']);
    Route::post('/mapa/estado', [MapaController::class, 'UserStatus']);
    // 4. Activar/Desactivar pánico
    Route::post('/mapa/panico', [MapaController::class, 'On_User_alert']);
    // Actualizar datos del usuario
    Route::match(['post', 'put'], '/update', [loginController::class, 'edit_user']);
    Route::get('/user/{id}', [loginController::class, 'show_user']);

    // solicita prestamo
    Route::get('/solicita-user', [prestamoController::class, 'solicitar_credito']);
    // 2. Generar opciones de préstamo y crear solicitud
    Route::post('/genera-opciones', [prestamoController::class, 'generar_solicitud']);

    // Abonar Pagos
    Route::post('/registrar-pago', [pagoController::class, 'registrarPago']);

    Route::get('/consultar-estado-cuenta-user', [prestamoController::class, 'consultarEstadoCuenta']);

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
    Route::get('/prestamo/show', [adminController::class, 've_prestamos']);

    // 3. Genera pdf usuarios (Solicitud de Prestamos)
    Route::get('/prestamos/{id}/pdf/{tipo}', [adminController::class, 'descargarPdfPrestamo'])
        ->where('tipo', 'aprobacion|liquidacion|rechazo');

    Route::post('/livekit/token', [LiveKitController::class, 'Get_Token']);

    Route::get('/grupo/{id}', [loginController::class, 'show_grupo']);
    Route::delete('/user/delete-account', [loginController::class, 'delete_account']);
    Route::middleware('auth:sanctum')->post('/admin/user/reactivate', [loginController::class, 'reactivate_account']);

    Route::post('/change-password', [passwordController::class, 'cambiar_password']);

    // comunidad
    Route::prefix('publication')->group(function () {
        Route::get('/', [publicacionController::class, 'index']);
        Route::post('/save', [publicacionController::class, 'store']);
        Route::delete('/delete/{id}', [publicacionController::class, 'destroy']);
        Route::post('/comment/{post}', [commentController::class, 'store']);
        Route::put('/update/{id}', [publicacionController::class, 'update']);
        Route::delete('/comment/{id}', [commentController::class, 'destroy']);
        Route::put('/comment/{id}', [commentController::class, 'update']);
        Route::post('/{id}/reaccionar', [publicacionController::class, 'reaccionar']);
    });
});


// ============================================================
// RUTAS PÚBLICAS (SIN AUTH)
// ============================================================

// Mercado Pago
Route::get('/consultar-pago-mp', [MercadoPagoController::class, 'consultarPagoMercadoPago']);
Route::get('/consultar-preferencia-mp', [MercadoPagoController::class, 'consultarPreferenciaMercadoPago']);

// Auth
Route::post('/login', [loginController::class, 'login']);
Route::post('/register', [loginController::class, 'register']);
Route::post('/register_admin', [loginController::class, 'register_admin']);
Route::post('/register_comercio', [loginController::class, 'register_comercio']);
Route::post('/admin/register', [adminController::class, 'index']);

// Password
Route::post('/forgot-password', [passwordController::class, 'olvide_password'])
    ->middleware('throttle:3,1');
Route::post('/new-password', [passwordController::class, 'nueva_password']);
Route::post('/ads/webhook', [AdController::class, 'webhook']);
// Pagos
Route::post('/verificar_pago', [pagoController::class, 'verificarPago']);

Route::post('/contacto', [ContactoController::class, 'store'])
    ->middleware('throttle:10,15'); // 10 intentos cada 15 min por IP
