<?php

use App\Http\Controllers\initController;
use Illuminate\Http\Request;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/mapa-datos/{cadena}',[initController::class,'mapa_datos']);
Route::get('/tablas', [initController::class,'index']);
Route::get('/mapa-datos', [initController::class,'mapa_datos']);
Route::get('/columnas', [initController::class,'TAblass']);

