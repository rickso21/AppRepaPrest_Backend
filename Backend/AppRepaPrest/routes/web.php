<?php

use App\Http\Controllers\image\ImageUploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/**
 * Habilitamos metodos para solo usar S3 sin validacion
 */
Route::get('image-upload', [ImageUploadController::class, 'image_upload']);
Route::post('image-upload', [ImageuploadController::class, 'upload_post_image'])->name('upload.post.image');
