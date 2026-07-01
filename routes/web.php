<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebInstallerController;

Route::get('/', function () {
    // Se não estiver instalado, redireciona pro instalador
    if (!file_exists(storage_path('app/installed.txt'))) {
        return redirect('/install');
    }
    return redirect('/admin');
});

Route::get('/install', [WebInstallerController::class, 'install']);
Route::post('/install', [WebInstallerController::class, 'storeInstall']);

Route::get('/uninstall', [WebInstallerController::class, 'uninstall']);
Route::post('/uninstall', [WebInstallerController::class, 'storeUninstall']);
