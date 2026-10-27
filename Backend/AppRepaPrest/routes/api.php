<?php

use App\Http\Controllers\mapa\MapaController;
use App\Http\Controllers\LiveKitController;
use App\Http\Controllers\prestamo\prestamoController;
use App\Http\Controllers\admin\adminController;
use App\Http\Controllers\soporte\SoporteController;
use App\Http\Controllers\pago\pagoController;
use App\Http\Controllers\MercadoPagoAllExterno\MercadoPagoController;
use App\Http\Controllers\tranferencia\tranferenciaController;
use App\Http\Controllers\user\loginController;
use App\Http\Controllers\user\passwordController;
use App\Http\Controllers\comunidad\publicacionController;
use App\Http\Controllers\comunidad\commentController;
use App\Http\Controllers\comunidad\AdController;
use App\Http\Controllers\contacto\ContactoController;
use App\Http\Controllers\comunidad\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('ads')->group(function () {
        Route::get('/', [AdController::class, 'index']);
        Route::get('/my-ads', [AdController::class, 'myAds']);
        Route::post('/create', [AdController::class, 'create']);
        Route::post('/{id}/pay', [AdController::class, 'pay']);
        Route::get('/{id}/payment-status', [AdController::class, 'paymentStatus']);


        Route::post('/{id}/impression', [AdController::class, 'impression']);
        Route::post('/{id}/click', [AdController::class, 'click']);
        Route::match(['put', 'post'], '/{id}/update', [AdController::class, 'update']);


    });


    Route::match(['post', 'put'], '/merchant/update', [loginController::class, 'update_merchant']);
    Route::delete('/merchant/delete', [loginController::class, 'delete_merchant']);

    // RUTAS DE SUSCRIPCIÓN
    Route::prefix('subscription')->group(function () {
        Route::post('/redeem', [SubscriptionController::class, 'redeem']);
        Route::post('/renew',  [SubscriptionController::class, 'renew']);
        Route::get('/status',  [SubscriptionController::class, 'status']);
        Route::get('/available-coupons', [SubscriptionController::class, 'availableCoupons']);
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
    Route::post('/user/push-token', [loginController::class, 'savePushToken']);
    Route::delete('/user/push-token', [loginController::class, 'deletePushToken']);
    Route::post('/user/push-token/remove', [loginController::class, 'deletePushToken']);
    Route::post('/logout', [loginController::class, 'logout']);


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
    Route::post('/admin/user/reactivate', [loginController::class, 'reactivate_account']);
    Route::post('/change-password', [passwordController::class, 'cambiar_password']);
    Route::get('/grupo/{id}/miembros', [loginController::class, 'miembros_grupo']);





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

//soporte
Route::get('/soporte', [SoporteController::class, 'show']);

// Mercado Pago
Route::get('/consultar-pago-mp', [MercadoPagoController::class, 'consultarPagoMercadoPago']);
Route::get('/consultar-preferencia-mp', [MercadoPagoController::class, 'consultarPreferenciaMercadoPago']);

// Auth (con rate limiting)
Route::post('/login', [loginController::class, 'login'])
    ->middleware('throttle:5,15');

Route::post('/register', [loginController::class, 'register'])
    ->middleware('throttle:3,60');

Route::post('/register_admin', [loginController::class, 'register_admin'])
    ->middleware('throttle:3,60');

Route::post('/register_comercio', [loginController::class, 'register_comercio'])
    ->middleware('throttle:3,60');

Route::post('/admin/register', [adminController::class, 'index'])
    ->middleware('throttle:3,60');

// Password (protegido)
Route::post('/forgot-password', [passwordController::class, 'olvide_password'])
    ->middleware('throttle:3,60');

Route::post('/new-password', [passwordController::class, 'nueva_password'])
    ->middleware('throttle:5,15');

// Pagos (protegido)
Route::post('/verificar_pago', [pagoController::class, 'verificarPago'])
    ->middleware('throttle:10,15');

Route::post('/contacto', [ContactoController::class, 'store'])
    ->middleware('throttle:10,15');
