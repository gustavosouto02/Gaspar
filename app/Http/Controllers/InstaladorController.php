<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Enums\UserRoleEnum;
use PDO;
use Exception;

class InstaladorController extends Controller
{
    /**
     * Exibe o formulário de instalação.
     */
    public function instalar()
    {
        if ($this->isInstalled()) {
            return redirect(url('admin'));
        }

        return view('instalar');
    }

    /**
     * Processa a instalação do sistema.
     *
     * Fluxo: validação → teste de conexão → criação do banco →
     * migrações → criação do admin → trava → resposta HTTP →
     * DEPOIS grava o .env (evita reinício do servidor antes da resposta).
     */
    public function executarInstalacao(Request $request)
    {
        // Evita timeout durante as migrações em servidores lentos (HostGator, etc.)
        @set_time_limit(300);

        if ($this->isInstalled()) {
            return redirect(url('admin'));
        }

        $request->validate([
            'db_host'     => 'required|string',
            'db_port'     => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:64',
            'db_username' => 'required|string',
        ]);

        // 1. Testa conexão MySQL e cria o banco se não existir
        try {
            $dsn = "mysql:host={$request->db_host};port={$request->db_port}";
            $pdo = new PDO($dsn, $request->db_username, $request->db_password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $dbName = $request->db_database;
            $stmt = $pdo->prepare(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?'
            );
            $stmt->execute([$dbName]);

            if (! $stmt->fetch()) {
                $safeName = '`' . str_replace('`', '``', $dbName) . '`';
                $pdo->exec("CREATE DATABASE {$safeName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        } catch (\Throwable $e) {
            return response('<h1>Erro de Conexão com Banco</h1><p>' . $e->getMessage() . '</p><pre>' . $e->getTraceAsString() . '</pre>', 500);
        }

        // 2. Reconfigura a conexão do banco em runtime (sem tocar no .env ainda!)
        config([
            'database.connections.mysql.host'     => $request->db_host,
            'database.connections.mysql.port'     => $request->db_port,
            'database.connections.mysql.database' => $request->db_database,
            'database.connections.mysql.username' => $request->db_username,
            'database.connections.mysql.password' => $request->db_password ?? '',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        try {
            // 3. Roda as migrações (cria todas as tabelas)
            Artisan::call('migrate:fresh', ['--force' => true]);

            // 4. Cria o usuário Administrador (automático)
            User::create([
                'name'      => 'Administrador Gaspar',
                'email'     => 'admin@gaspar.com',
                'password'  => Hash::make('admin'),
                'user_role' => UserRoleEnum::ADMIN,
            ]);

            // 5. Cria o link simbólico do storage (essencial para hospedagens como HostGator)
            try {
                Artisan::call('storage:link');
            } catch (\Exception $e) {
                // Silencioso se o link já existir
            }

            // 6. Cria a trava de instalação
            file_put_contents(storage_path('app/installed.txt'), now()->toIso8601String());

        } catch (\Throwable $e) {
            // Se falhar na migração/banco, retorna o erro diretamente na tela.
            // Não usamos back()->with() porque se houver problema de permissão na pasta sessions,
            // o back()->with() vai causar um Erro 500 (Jacaré) escondendo o erro real.
            return response('<h1>Erro na Instalação</h1><p>' . $e->getMessage() . '</p><pre>' . $e->getTraceAsString() . '</pre>', 500);
        }

        // 7. Registra a gravação do .env para DEPOIS de a resposta ser enviada ao navegador.
        // O callback app()->terminating() roda após o Laravel enviar a resposta HTTP completa.
        // Isso evita que a escrita do .env mate a conexão antes da página de sucesso aparecer.
        $envData = [
            'APP_NAME'         => 'Gaspar',
            'DB_CONNECTION'    => 'mysql',
            'DB_HOST'          => $request->db_host,
            'DB_PORT'          => $request->db_port,
            'DB_DATABASE'      => $request->db_database,
            'DB_USERNAME'      => $request->db_username,
            'DB_PASSWORD'      => $request->db_password ?? '',
            'SESSION_DRIVER'   => 'database',
            'CACHE_STORE'      => 'database',
            'QUEUE_CONNECTION' => 'database',
        ];

        app()->terminating(function () use ($envData) {
            $this->updateEnv($envData);

            try {
                Artisan::call('config:clear');
            } catch (\Throwable $t) {
                // Silencioso: se o servidor reiniciar aqui, não importa — a página já foi entregue
            }
        });

        // 8. Retorna a resposta de sucesso (o Laravel envia ao navegador normalmente)
        return response()->view('instalado', [
            'admin_name'     => 'Administrador Gaspar',
            'admin_email'    => 'admin@gaspar.com',
            'admin_password' => 'admin',
        ]);
    }

    /**
     * Exibe a tela de confirmação de desinstalação.
     */
    public function desinstalar()
    {
        if (!$this->isInstalled()) {
            return redirect()->route('instalar');
        }

        return view('desinstalar');
    }

    /**
     * Processa a desinstalação do sistema.
     *
     * Apaga todas as tabelas do banco, remove a trava de instalação
     * e o arquivo .env, liberando o sistema para uma nova instalação.
     */
    public function executarDesinstalacao(Request $request)
    {
        if (!$this->isInstalled()) {
            return redirect()->route('instalar');
        }

        try {
            // HACK DEFINITIVO: O Laravel tenta salvar a sessão no banco no final do request.
            // Para evitar o erro "Table sessions/users doesn't exist", mudamos a config
            // em runtime e forçamos o SessionManager a esquecer o driver do banco e usar memória.
            config(['session.driver' => 'array']);
            app('session')->forgetDrivers();

            // 1. Apaga todas as tabelas do banco
            Artisan::call('db:wipe', ['--force' => true]);
        } catch (\Throwable $e) {
            // Se falhar ao apagar o banco, ainda prossegue com a limpeza local
        }

        // 2. Remove a trava de instalação
        $installedFile = storage_path('app/installed.txt');
        if (file_exists($installedFile)) {
            unlink($installedFile);
        }

        // 3. Remove o .env para forçar nova instalação limpa
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            unlink($envPath);
        }

        // 4. Limpa caches do framework
        try {
            Artisan::call("config:clear");
        } catch (Exception $e) {
            // Ignora erros de cache (o sistema está sendo limpo)
        }

        // Redireciona de volta para a tela de instalação
        return redirect()->route('instalar', ['desinstalado' => 1]);
    }

    /**
     * Verifica se o sistema já está instalado.
     */
    protected function isInstalled(): bool
    {
        return file_exists(storage_path('app/installed.txt'));
    }

    /**
     * Atualiza valores no arquivo .env
     *
     * Se a chave já existir, substitui o valor.
     * Se não existir, adiciona ao final do arquivo.
     */
    protected function updateEnv(array $data): void
    {
        $path = base_path('.env');

        if (!file_exists($path)) {
            $examplePath = base_path('.env.example');
            if (file_exists($examplePath)) {
                copy($examplePath, $path);
            } else {
                return;
            }
        }

        $content = file_get_contents($path);

        foreach ($data as $key => $value) {
            // Escapa valores que contêm espaços ou caracteres especiais
            $escapedValue = $value;
            if (str_contains($value, ' ') || str_contains($value, '#') || $value === '') {
                $escapedValue = '"' . $value . '"';
            }

            $pattern = "/^{$key}=.*/m";
            $commentedPattern = "/^#\s*{$key}=.*/m";

            if (preg_match($pattern, $content)) {
                // Substitui valor existente
                $content = preg_replace($pattern, "{$key}={$escapedValue}", $content);
            } elseif (preg_match($commentedPattern, $content)) {
                // Descomenta e define o valor
                $content = preg_replace($commentedPattern, "{$key}={$escapedValue}", $content);
            } else {
                // Adiciona ao final
                $content .= PHP_EOL . "{$key}={$escapedValue}";
            }
        }

        file_put_contents($path, $content);
    }
}
