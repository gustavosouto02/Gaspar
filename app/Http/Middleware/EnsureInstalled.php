<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    /**
     * Rotas que o instalador precisa acessar mesmo sem o sistema instalado.
     */
    protected array $installerRoutes = [
        'instalar',
    ];

    /**
     * Handle an incoming request.
     *
     * Detecta o estado de instalação do sistema e controla o acesso
     * às rotas. A config de session/cache é tratada no AppServiceProvider.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isInstalled = file_exists(storage_path('app/installed.txt'));
        $currentPath = trim($request->path(), '/');

        if (! $isInstalled) {
            // Se NÃO está numa rota do instalador, redireciona
            if (! $this->isInstallerRoute($currentPath)) {
                return redirect()->route('instalar');
            }
        } else {
            // Sistema instalado: bloqueia acesso à rota de instalação
            if ($currentPath === 'instalar') {
                return redirect(url('admin'));
            }
        }

        return $next($request);
    }

    /**
     * Verifica se a rota atual é uma rota do instalador.
     */
    protected function isInstallerRoute(string $path): bool
    {
        foreach ($this->installerRoutes as $route) {
            if ($path === $route) {
                return true;
            }
        }

        return false;
    }
}

