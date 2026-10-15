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

// Rotas restritas exclusivamente a administradores autenticados
Route::middleware([\App\Http\Middleware\EnsureAdmin::class])->group(function () {
    Route::get('/desinstalar', [InstaladorController::class, 'desinstalar'])->name('desinstalar');
    Route::post('/desinstalar', [InstaladorController::class, 'executarDesinstalacao'])->name('desinstalar.post');

    Route::post('/update-system', function () {
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true]);
        return 'Sistema atualizado, cache limpo, migrações sincronizadas e fila processada com sucesso!';
    })->name('admin.update-system');

    Route::post('/trigger-deadlines', function () {
        \Illuminate\Support\Facades\Artisan::call('app:check-demand-deadlines', ['--force' => true]);
        $deadlinesOutput = \Illuminate\Support\Facades\Artisan::output();

        \Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true]);
        $queueOutput = \Illuminate\Support\Facades\Artisan::output();

        return response()->json([
            'status' => 'success',
            'message' => 'Verificação de prazos e envio executados com sucesso.',
            'deadlines' => $deadlinesOutput,
            'queue' => $queueOutput,
        ]);
    })->name('admin.trigger-deadlines');

    Route::post('/test-email', function (\Illuminate\Http\Request $request) {
        $to = $request->input('to', $request->query('to', auth()->user()?->email ?? config('mail.from.address')));
        try {
            \Illuminate\Support\Facades\Mail::raw("Olá! Este é um e-mail de teste disparado com sucesso pelo Gaspar através do servidor SMTP configurado em " . now()->format('d/m/Y H:i:s') . ".\n\nSe você recebeu este e-mail, as configurações de SMTP estão 100% corretas e ativas!", function ($message) use ($to) {
                $message->to($to)
                        ->subject('Teste de Conexão SMTP - Sistema Gaspar');
            });

            return response()->json([
                'status' => 'success',
                'message' => "E-mail de teste enviado com sucesso para {$to}!",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao conectar ou enviar via SMTP: ' . $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    })->name('admin.test-email');
});

Route::get('/demands/{record}/pdf', [\App\Http\Controllers\DemandPdfController::class, 'export'])
    ->name('demands.pdf')
    ->middleware(['auth']);

