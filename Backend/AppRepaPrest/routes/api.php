<?php

use App\Http\Controllers\image\ImageUploadController;
use App\Http\Controllers\prueba\PruebaController;
use App\Http\Controllers\login\UserApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
/**
 * Recibimos datos del usuario para enviar SMS
 */
Route::group(['middleware' => ['auth:sanctum']], function () {
	Route::post('test', [PruebaController::class, 'index']);

});
/**
 * Habilitamos metodo para loguearse y poder consumir API
 */
Route::post('login', [UserApiController::class, 'login']);
Route::post('registro', [UserApiController::class, 'registro']);
Route::post('image-list', [ImageUploadController::class, 'show']);
Route::post('sube_archivos', [ImageUploadController::class, 'sube_archivos_s3']);

