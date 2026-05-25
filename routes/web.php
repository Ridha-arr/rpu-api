<?php

use App\Http\Controllers\API\PublikasiController;
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


Route::get('/login/keycloak/redirect', [App\Http\Controllers\API\AuthController::class, 'redirectToKeycloak'])->name('keycloak.redirect');
Route::get('/login/keycloak/callback', [App\Http\Controllers\API\AuthController::class, 'handleKeycloakCallback'])->name('keycloak.callback');
