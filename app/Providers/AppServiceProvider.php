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
        // A HostGator usa o cabeçalho 'x-https' ou 'HTTPS'='on' para indicar SSL.
        // Como o Laravel não confia em proxies desconhecidos por padrão, ele acaba
        // gerando URLs com http://. Isso causa erro de "Mixed Content" no Chrome,
        // bloqueando o JS do Livewire e quebrando a tela de login.
        if (request()->header('x-https') == '1' || isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
