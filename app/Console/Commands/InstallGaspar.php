<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallGaspar extends Command
{
    protected $signature = 'gaspar:install';

    protected $description = 'Instala e configura o sistema Gaspar iterativamente.';

    public function handle()
    {
        \Laravel\Prompts\info('Bem-vindo ao instalador do Gaspar! 🚀');

        // Configuração do Banco de Dados
        \Laravel\Prompts\info('=== Configuração do Banco de Dados (MySQL) ===');
        $dbHost = \Laravel\Prompts\text('Host do Banco de Dados', default: '127.0.0.1');
        $dbPort = \Laravel\Prompts\text('Porta do Banco de Dados', default: '3306');
        $dbDatabase = \Laravel\Prompts\text('Nome do Banco de Dados', default: 'gaspar');
        $dbUsername = \Laravel\Prompts\text('Usuário do Banco de Dados', default: 'root');
        $dbPassword = \Laravel\Prompts\password('Senha do Banco de Dados (deixe em branco se não houver)');

        // Atualiza o arquivo .env
        $this->updateEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $dbDatabase,
            'DB_USERNAME' => $dbUsername,
            'DB_PASSWORD' => $dbPassword,
        ]);

        \Laravel\Prompts\info('Credenciais salvas no .env!');

        // Cria o banco se não existir via PDO direto, pois o config:clear pode não surtir efeito 
        // no mesmo ciclo de vida para a conexão padrão
        try {
            $pdo = new \PDO("mysql:host={$dbHost};port={$dbPort}", $dbUsername, $dbPassword);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbDatabase}'");
            if (! $stmt->fetch()) {
                \Laravel\Prompts\info("Criando banco de dados '{$dbDatabase}'...");
                $pdo->exec("CREATE DATABASE `{$dbDatabase}`");
            }
        } catch (\Throwable $e) {
            $this->error('Falha ao conectar no servidor MySQL. Verifique as credenciais e tente novamente.');
            $this->error($e->getMessage());
            return;
        }

        // Limpar cache
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        
        // Força a atualização da config em memória para que o migrate rode no banco recém-configurado
        config(['database.connections.mysql.host' => $dbHost]);
        config(['database.connections.mysql.port' => $dbPort]);
        config(['database.connections.mysql.database' => $dbDatabase]);
        config(['database.connections.mysql.username' => $dbUsername]);
        config(['database.connections.mysql.password' => $dbPassword]);
        \Illuminate\Support\Facades\DB::purge('mysql');
        \Illuminate\Support\Facades\DB::reconnect('mysql');

        \Laravel\Prompts\info('Executando migrações do banco de dados (pode demorar alguns segundos)...');
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
        $this->line(\Illuminate\Support\Facades\Artisan::output());

        // Criação do Admin
        \Laravel\Prompts\info('=== Criação do Usuário Administrador ===');
        $adminName = \Laravel\Prompts\text('Nome do Administrador', default: 'Admin Gaspar');
        $adminEmail = \Laravel\Prompts\text('E-mail do Administrador', default: 'admin@gaspar.local');
        $adminPassword = \Laravel\Prompts\password('Senha do Administrador (mínimo 8 caracteres)') ?: 'password';

        \App\Models\User::create([
            'name' => $adminName,
            'email' => $adminEmail,
            'password' => \Illuminate\Support\Facades\Hash::make($adminPassword),
            'user_role' => \App\Enums\UserRoleEnum::ADMIN,
        ]);

        \Laravel\Prompts\info('Instalação concluída com sucesso! 🎉');
        $this->line('');
        $this->line('================================================');
        $this->line('🟢 Para acessar o sistema na reunião:');
        $this->info("   Login: {$adminEmail}");
        $this->info("   Senha: (A que você acabou de digitar)");
        $this->line('================================================');
        $this->line('');
        $this->line('Para iniciar o servidor, rode:');
        $this->info('php artisan serve');
        $this->line('');
    }

    protected function updateEnv(array $data)
    {
        $path = base_path('.env');
        if (! file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$value}", $content);
            } else {
                $content .= PHP_EOL . "{$key}={$value}";
            }
        }

        file_put_contents($path, $content);
    }
}
