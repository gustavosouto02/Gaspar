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
     * escrita do .env → migrações → criação do admin → trava.
     */
    public function executarInstalacao(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect(url('admin'));
        }

        $request->validate([
            'db_host'        => 'required|string',
            'db_port'        => 'required|integer|min:1|max:65535',
            'db_database'    => 'required|string|max:64',
            'db_username'    => 'required|string',
            'admin_name'     => 'required|string|max:255',
            'admin_email'    => 'required|email|max:255',
            'admin_password' => 'required|string|min:8|max:255',
        ]);

        // 1. Testa conexão MySQL e cria o banco se não existir
        try {
            $dsn = "mysql:host={$request->db_host};port={$request->db_port}";
            $pdo = new PDO($dsn, $request->db_username, $request->db_password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Verifica se o banco já existe
            $dbName = $request->db_database;
            $stmt = $pdo->prepare(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?'
            );
            $stmt->execute([$dbName]);

            if (! $stmt->fetch()) {
                // Sanitiza o nome do banco para evitar injeção SQL
                $safeName = '`' . str_replace('`', '``', $dbName) . '`';
                $pdo->exec("CREATE DATABASE {$safeName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Falha ao conectar no MySQL. Verifique as credenciais. Erro: ' . $e->getMessage());
        }

        // 2. Atualiza o .env com as credenciais do banco
        $this->updateEnv([
            'APP_NAME'      => 'Gaspar',
            'DB_CONNECTION'  => 'mysql',
            'DB_HOST'        => $request->db_host,
            'DB_PORT'        => $request->db_port,
            'DB_DATABASE'    => $request->db_database,
            'DB_USERNAME'    => $request->db_username,
            'DB_PASSWORD'    => $request->db_password ?? '',
        ]);

        // 3. Limpa o cache de configuração
        Artisan::call("config:clear"); \Log::info("Session Driver After config:clear: " . config("session.driver"));

        // 4. Gera a APP_KEY se ainda não existir
        if (empty(config('app.key')) || config('app.key') === '') {
            Artisan::call('key:generate', ['--force' => true]);
        }

        // 5. Reconfigura a conexão do banco em runtime
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
            // 6. Roda as migrações (cria todas as tabelas)
            Artisan::call('migrate:fresh', ['--force' => true]);

            // 7. Cria o usuário Administrador
            $admin = User::create([
                'name'      => $request->admin_name,
                'email'     => $request->admin_email,
                'password'  => Hash::make($request->admin_password),
                'user_role' => UserRoleEnum::ADMIN,
            ]);

            // 8. Cria a trava de instalação
            file_put_contents(storage_path('app/installed.txt'), now()->toIso8601String());

        } catch (Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Erro durante a criação das tabelas ou do administrador: ' . $e->getMessage());
        }

        // 9. Exibe a tela de sucesso com os dados do admin
        return view('instalado', [
            'admin_name'     => $request->admin_name,
            'admin_email'    => $request->admin_email,
            'admin_password' => $request->admin_password,
        ]);
    }

    /**
     * Exibe a tela de confirmação de desinstalação.
     */
    public function desinstalar()
    {
        if (! $this->isInstalled()) {
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
        if (! $this->isInstalled()) {
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
            Artisan::call("config:clear"); \Log::info("Session Driver After config:clear: " . config("session.driver"));
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

        if (! file_exists($path)) {
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
