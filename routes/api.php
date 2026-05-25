<?php

use App\Http\Controllers\API\AwardController;
use App\Http\Controllers\API\BukuController;
use App\Http\Controllers\API\DataController;
use App\Http\Controllers\API\JurnalController;
use App\Http\Controllers\API\KaryaController;
use App\Http\Controllers\API\NonPublishController;
use App\Http\Controllers\API\OpiniController;
use App\Http\Controllers\API\PatenController;
use App\Http\Controllers\API\PosterController;
use App\Http\Controllers\API\ProfilController;
use App\Http\Controllers\API\ProsidingController;
use App\Http\Controllers\API\PublikasiController;
use App\Http\Controllers\API\ReviewerController;
use App\Http\Controllers\API\SeminarController;
use App\Http\Controllers\API\WorkshopsController;
use App\Http\Controllers\MigrateController;
use App\Models\NonPublish;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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


//API route for register new user
Route::post('/register', [App\Http\Controllers\API\AuthController::class, 'register']);
//API route for login user
Route::post('/login/keycloak', [App\Http\Controllers\API\AuthController::class, 'keycloakLogin'])->middleware('throttle:10,1');
Route::post('/login/keycloak/callback', [App\Http\Controllers\API\AuthController::class, 'keycloakCallbackLogin'])->middleware('throttle:10,1');
Route::get('/cache', function () {
    Artisan::call('config:cache');
});
Route::get('/user-view/{id}', function ($id) {
    return User::with(['profile', 'biodata.instansi', 'biodata:tempat_lahir,nidon,instansi,nip,nama,gelar_depan,gelar_belakang,tanggal_lahir', 'pendidikan_s1', 'pendidikan_s2', 'pendidikan_s3'])
        ->select(
            'id',
            'nip',
            'email',
            'nama'
        )->withCount([
            'jurnal',
            'prosiding',
            'seminar',
            'buku',
            'paten',
            'nonPublish',
            'opini',
            'karya',
            'poster'
        ])->find($id);
});
Route::get('/user-get/{id}', [ProfilController::class, 'index']);
Route::get('/user/{alias}/publikasi', [DataController::class, 'publikasiByAlias']);
Route::get('/user/{id}', [DataController::class, 'user']);
Route::get('/publikasi-user/{id}', [DataController::class, 'publikasiASId']);
Route::get('/data-get', [DataController::class, 'public']);
Route::get('/header-stats-get', [DataController::class, 'headerStats']);
Route::get('/dataall-get', [DataController::class, 'all']);
Route::get('/publikasiall-get/{state}', [DataController::class, 'publikasiall']);
Route::get('/repo', [DataController::class, 'repo']);
Route::get('/cari/{search}', [DataController::class, 'cari']);
Route::get('/publikasi/{nama}/{publikasi}', [DataController::class, 'publikasi']);

//Protecting Routes
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/biodata', function (Request $request) {
        return User::with(['profile.fakultas', 'profile.prodi', 'pendidikan'])
            ->select(
                'id',
                'username',
                'alias',
                'email',
                'name'
            )->find(auth()->user()->id);
    });
    Route::post('/profile-post', [ProfilController::class, 'store']);
    Route::get('/profile-get', [ProfilController::class, 'show']);
    Route::post('/publikasi-post', [PublikasiController::class, 'store']);
    Route::get('/publikasi-get/{id}', [PublikasiController::class, 'show']);
    Route::get('/publikasi-count', [PublikasiController::class, 'count']);
    Route::post('/publikasi-update/{id}', [PublikasiController::class, 'update']);
    Route::delete('/publikasi-delete/{id}', [PublikasiController::class, 'destroy']);

    Route::get('/data', [DataController::class, 'index']);
    Route::post('/data/sync', [DataController::class, 'sync'])->middleware('throttle:5,1');

    Route::post('/fileUpload', [DataController::class, 'file']);
    Route::post('/logout', [App\Http\Controllers\API\AuthController::class, 'logout']);

    Route::get('/migrate', [MigrateController::class, 'index']);
    Route::get('/sister/referensi/kategori-kegiatan-publikasi', [DataController::class, 'kategoriKegiatanPublikasi'])->middleware('throttle:20,1');
});
Route::get('/tahun', [DataController::class, 'tahun']);
Route::get('/fakultas', [DataController::class, 'fakultas']);
Route::get('/prodi/{fakultas_id}', [DataController::class, 'prodi']);
Route::get('/jenis-publikasi', [DataController::class, 'jenisPublikasi']);
Route::get('/grafikJurnal', [DataController::class, 'GrafikJurnal']);
Route::get('/sync/fakultas', [DataController::class, 'syncFakultas']);
Route::get('/sync/prodi', [DataController::class, 'syncProdi']);
Route::get('/data-fsd/{alias}', [DataController::class, 'dataFSD']);
