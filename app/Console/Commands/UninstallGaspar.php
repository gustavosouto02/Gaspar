<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UninstallGaspar extends Command
{
    protected $signature = 'gaspar:uninstall';

    protected $description = 'Remove todos os dados e tabelas do Gaspar.';

    public function handle()
    {
        \Laravel\Prompts\warning('Atenção: A desinstalação removerá TODAS as tabelas e dados do banco de dados configurado no .env.');
        
        $confirm = \Laravel\Prompts\confirm(
            label: 'Tem certeza disso? Essa ação é irreversível.',
            default: false,
            yes: 'Sim, destrua tudo!',
            no: 'Não, cancelar'
        );

        if (! $confirm) {
            \Laravel\Prompts\info('Desinstalação cancelada. Seus dados estão seguros.');
            return;
        }

        \Laravel\Prompts\info('Apagando o banco de dados...');
        \Illuminate\Support\Facades\Artisan::call('db:wipe', ['--force' => true]);
        
        $this->line(\Illuminate\Support\Facades\Artisan::output());
        \Laravel\Prompts\info('Desinstalação concluída. O banco está limpo!');
    }
}
