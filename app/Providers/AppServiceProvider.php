<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Roda ANTES de qualquer middleware. Quando o sistema ainda não
     * está instalado, o banco não existe — então forçamos session e
     * cache para 'file' para evitar erros de conexão e CSRF 419.
     */
    public function register(): void
    {
        if (! file_exists(storage_path('app/installed.txt'))) {
            config([
                'session.driver' => 'file',
                'cache.default'  => 'file',
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
