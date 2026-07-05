<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InstaladorController;

Route::get('/', function () {
    if (! file_exists(storage_path('app/installed.txt'))) {
        return redirect('/instalar');
    }

    return redirect('/admin');
});

Route::get('/instalar', [InstaladorController::class, 'instalar']);
Route::post('/instalar', [InstaladorController::class, 'executarInstalacao']);

Route::get('/desinstalar', [InstaladorController::class, 'desinstalar']);
Route::post('/desinstalar', [InstaladorController::class, 'executarDesinstalacao']);
