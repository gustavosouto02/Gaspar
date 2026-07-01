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

class WebInstallerController extends Controller
{
    /**
     * Exibe o formulário de instalação.
     */
    public function install()
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        return view('install');
    }

    /**
     * Processa os dados de instalação.
     */
    public function storeInstall(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $request->validate([
            'db_host' => 'required',
            'db_port' => 'required',
            'db_database' => 'required',
            'db_username' => 'required',
            'admin_name' => 'required',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:8',
        ]);

        try {
            // Tenta criar o banco de dados via PDO (caso não exista)
            $pdo = new PDO(
                "mysql:host={$request->db_host};port={$request->db_port}",
                $request->db_username,
                $request->db_password
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$request->db_database}'");
            if (! $stmt->fetch()) {
                $pdo->exec("CREATE DATABASE `{$request->db_database}`");
            }
        } catch (Exception $e) {
            return back()->with('error', 'Falha ao conectar no MySQL. Verifique as credenciais. Erro: ' . $e->getMessage());
        }

        // Atualiza o arquivo .env
        $this->updateEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $request->db_host,
            'DB_PORT' => $request->db_port,
            'DB_DATABASE' => $request->db_database,
            'DB_USERNAME' => $request->db_username,
            'DB_PASSWORD' => $request->db_password,
        ]);

        // Limpa o cache para que o Laravel leia o novo .env
        Artisan::call('config:clear');

        // Se a chave não existir, gera silenciosamente (o Artisan vai injetar no .env automaticamente)
        Artisan::call('key:generate', ['--force' => true]);

        // Configura a conexão em memória para este ciclo de execução
        config(['database.connections.mysql.host' => $request->db_host]);
        config(['database.connections.mysql.port' => $request->db_port]);
        config(['database.connections.mysql.database' => $request->db_database]);
        config(['database.connections.mysql.username' => $request->db_username]);
        config(['database.connections.mysql.password' => $request->db_password]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        try {
            // Roda as migrações (Cria as tabelas)
            Artisan::call('migrate:fresh', ['--force' => true]);

            // Cria o Administrador
            User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'user_role' => UserRoleEnum::ADMIN,
            ]);

            // Cria a trava de segurança
            file_put_contents(storage_path('app/installed.txt'), 'installed');

        } catch (Exception $e) {
            return back()->with('error', 'Erro durante a criação das tabelas ou usuário: ' . $e->getMessage());
        }

        // Redireciona para o painel admin para o usuário fazer login
        return redirect('/admin')->with('success', 'Instalação concluída com sucesso!');
    }

    /**
     * Exibe a tela de desinstalação.
     */
    public function uninstall()
    {
        if (!$this->isInstalled()) {
            return redirect('/');
        }

        return view('uninstall');
    }

    /**
     * Processa a desinstalação.
     */
    public function storeUninstall(Request $request)
    {
        if (!$this->isInstalled()) {
            return redirect('/');
        }

        // Apaga todas as tabelas do banco
        Artisan::call('db:wipe', ['--force' => true]);

        // Remove a trava de segurança
        if (file_exists(storage_path('app/installed.txt'))) {
            unlink(storage_path('app/installed.txt'));
        }

        // Reseta o .env se desejar (opcional, mas aqui vamos só desinstalar os dados para ficar limpo)
        
        return redirect('/install')->with('success', 'Sistema desinstalado com sucesso!');
    }

    /**
     * Helper: Atualiza os valores do .env
     */
    protected function updateEnv(array $data)
    {
        $path = base_path('.env');
        if (! file_exists($path)) {
            // Se por algum motivo o .env não existir (usuário não copiou o example), tenta usar o example
            if (file_exists(base_path('.env.example'))) {
                copy(base_path('.env.example'), $path);
            } else {
                return;
            }
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

    /**
     * Helper: Verifica se o sistema já foi instalado.
     */
    protected function isInstalled()
    {
        return file_exists(storage_path('app/installed.txt'));
    }
}
