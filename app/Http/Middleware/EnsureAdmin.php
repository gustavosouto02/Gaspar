<?php

namespace App\Http\Middleware;

use App\Enums\UserRoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Garante que apenas usuários autenticados com papel ADMIN possam prosseguir.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            abort(403, 'Acesso não autorizado. Faça login como administrador para continuar.');
        }

        if (auth()->user()->user_role !== UserRoleEnum::ADMIN) {
            abort(403, 'Acesso restrito exclusivamente a administradores.');
        }

        return $next($request);
    }
}
