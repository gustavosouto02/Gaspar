<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InstaladorController;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    if (! file_exists(storage_path('app/installed.txt'))) {
        return redirect()->route('instalar');
    }

    return redirect(url('admin'));
});

Route::get('/instalar', [InstaladorController::class, 'instalar'])->name('instalar');
Route::post('/instalar', [InstaladorController::class, 'executarInstalacao'])->name('instalar.post');

Route::get('/desinstalar', [InstaladorController::class, 'desinstalar'])->name('desinstalar');
Route::post('/desinstalar', [InstaladorController::class, 'executarDesinstalacao'])->name('desinstalar.post');

Route::get('/update-system', function () {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    return 'Sistema atualizado, cache limpo e banco de dados sincronizado com sucesso!';
});

Route::get('/trigger-deadlines', function () {
    \Illuminate\Support\Facades\Artisan::call('app:check-demand-deadlines');
    return response()->json([
        'status' => 'success',
        'message' => 'Verificação de prazos executada com sucesso.',
        'output' => \Illuminate\Support\Facades\Artisan::output()
    ]);
});
