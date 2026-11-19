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
            'db_host'           => 'required|string',
            'db_port'           => 'required|integer|min:1|max:65535',
            'db_database'       => 'required|string|max:64',
            'db_username'       => 'required|string',
            'mail_host'         => 'nullable|string|max:255',
            'mail_port'         => 'nullable|integer|min:1|max:65535',
            'mail_username'     => 'nullable|string|max:255',
            'mail_password'     => 'nullable|string|max:255',
            'mail_encryption'   => 'nullable|string|in:ssl,tls,none',
            'mail_from_name'    => 'nullable|string|max:150',
            'mail_from_address' => 'nullable|string|max:255',
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
            // 1044: o usuário MySQL não pode criar bancos (padrão em cPanel/HostGator)
            if (($e instanceof \PDOException) && (int) ($e->errorInfo[1] ?? 0) === 1044) {
                return response(
                    '<h1>Banco de dados não encontrado</h1>'
                    . '<p>O banco <strong>' . e($request->db_database) . '</strong> não existe e este usuário MySQL não tem permissão para criá-lo '
                    . '(comum em hospedagens cPanel).</p>'
                    . '<p>Crie o banco em <strong>cPanel → Bancos de dados MySQL</strong>, adicione o usuário a ele com <em>Todos os privilégios</em> '
                    . 'e tente a instalação novamente.</p>'
                    . '<p><a href="javascript:history.back()">Voltar</a></p>',
                    422
                );
            }

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
            $admin = User::create([
                'name'      => 'Administrador Gaspar',
                'email'     => 'admin@gaspar.com',
                'password'  => Hash::make('admin'),
                'user_role' => UserRoleEnum::ADMIN,
            ]);

            // Vincula os status de sistema ao administrador
            try {
                DB::table('process_statuses')->whereNull('created_by')->update(['created_by' => $admin->id]);
            } catch (\Throwable $t) {
                // Silencioso
            }

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

        // 7. Normaliza as configurações de e-mail / SMTP
        $mailHost = $request->input('mail_host') ?: 'smtp.titan.email';
        $mailPort = (string) ($request->input('mail_port') ?: '465');
        $mailUsername = $request->input('mail_username') ?: '';
        $mailPassword = $request->input('mail_password') ?: '';
        $mailEncryption = $request->input('mail_encryption') ?: 'ssl';
        $mailFromName = $request->input('mail_from_name') ?: 'Sistema Gaspar';
        $mailFromAddress = $request->input('mail_from_address') ?: ($mailUsername ?: 'no-reply@gaspar.com.br');

        $mailScheme = ($mailEncryption === 'ssl' || $mailPort === '465') ? 'smtps' : 'smtp';
        if ($mailEncryption === 'none') {
            $mailEncryption = '';
            $mailScheme = 'smtp';
        }

        // 8. Registra a gravação do .env para DEPOIS de a resposta ser enviada ao navegador.
        // O callback app()->terminating() roda após o Laravel enviar a resposta HTTP completa.
        // Isso evita que a escrita do .env mate a conexão antes da página de sucesso aparecer.
        $envData = [
            'APP_NAME'          => 'Gaspar',
            'APP_URL'           => url('/'),
            'DB_CONNECTION'     => 'mysql',
            'DB_HOST'           => $request->db_host,
            'DB_PORT'           => $request->db_port,
            'DB_DATABASE'       => $request->db_database,
            'DB_USERNAME'       => $request->db_username,
            'DB_PASSWORD'       => $request->db_password ?? '',
            'SESSION_DRIVER'    => 'database',
            'CACHE_STORE'       => 'database',
            'QUEUE_CONNECTION'  => 'sync',
            'MAIL_MAILER'       => 'smtp',
            'MAIL_SCHEME'       => $mailScheme,
            'MAIL_HOST'         => $mailHost,
            'MAIL_PORT'         => $mailPort,
            'MAIL_USERNAME'     => $mailUsername,
            'MAIL_PASSWORD'     => $mailPassword,
            'MAIL_ENCRYPTION'   => $mailEncryption,
            'MAIL_FROM_ADDRESS' => $mailFromAddress,
            'MAIL_FROM_NAME'    => $mailFromName,
        ];

        app()->terminating(function () use ($envData) {
            $this->updateEnv($envData);

            try {
                Artisan::call('config:clear');
            } catch (\Throwable $t) {
                // Silencioso: se o servidor reiniciar aqui, não importa — a página já foi entregue
            }
        });

        // 9. Retorna a resposta de sucesso (o Laravel envia ao navegador normalmente)
        return response()->view('instalado', [
            'admin_name'        => 'Administrador Gaspar',
            'admin_email'       => 'admin@gaspar.com',
            'admin_password'    => 'admin',
            'mail_host'         => $mailHost,
            'mail_port'         => $mailPort,
            'mail_username'     => $mailUsername,
            'mail_encryption'   => $mailEncryption,
            'mail_from_address' => $mailFromAddress,
            'mail_from_name'    => $mailFromName,
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
     * Processa a desinstalação completa do sistema.
     *
     * 1. Apaga todas as tabelas do banco (db:wipe)
     * 2. Tenta excluir o banco de dados inteiro do servidor (DROP DATABASE)
     * 3. Remove a trava de instalação e o .env
     * 4. Apaga recursivamente todos os arquivos e a própria pasta do projeto no servidor
     */
    public function executarDesinstalacao(Request $request)
    {
        @set_time_limit(300);
        @ignore_user_abort(true);

        if (! $this->isInstalled()) {
            return redirect()->route('instalar');
        }

        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Apenas administradores autenticados podem executar a desinstalação.');
        }

        // Captura os dados de conexão antes de alterar configs ou remover o .env
        $dbHost = config('database.connections.mysql.host') ?: env('DB_HOST', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port') ?: env('DB_PORT', '3306');
        $dbName = config('database.connections.mysql.database') ?: env('DB_DATABASE');
        $dbUser = config('database.connections.mysql.username') ?: env('DB_USERNAME');
        $dbPass = config('database.connections.mysql.password') ?? env('DB_PASSWORD', '');

        $dbWiped = false;
        $dbDropped = false;

        try {
            // Muda sessão, cache e log para memória/null em runtime para que o Laravel
            // não tente gravar no banco nem em storage/ no encerramento do request.
            config([
                'session.driver'  => 'array',
                'cache.default'   => 'array',
                'logging.default' => 'null',
            ]);
            app('session')->forgetDrivers();

            // 1. Apaga todas as tabelas do banco
            Artisan::call('db:wipe', ['--force' => true]);
            $dbWiped = true;
        } catch (\Throwable $e) {
            // Prossegue mesmo se db:wipe falhar
        }

        // 2. Tenta excluir o banco de dados inteiro (DROP DATABASE)
        if (! empty($dbName) && ! empty($dbHost) && ! empty($dbUser) && config('database.default') === 'mysql') {
            try {
                DB::disconnect('mysql');
                $dsn = "mysql:host={$dbHost};port={$dbPort}";
                $pdo = new PDO($dsn, $dbUser, $dbPass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $safeName = '`' . str_replace('`', '``', $dbName) . '`';
                $pdo->exec("DROP DATABASE IF EXISTS {$safeName}");
                $dbDropped = true;
            } catch (\Throwable $e) {
                // Em hospedagens compartilhadas onde o usuário MySQL não tem permissão global de DROP DATABASE,
                // o db:wipe já garantiu a exclusão de todas as tabelas.
            }
        }

        // Define o diretório raiz que será limpo (em testes unitários, exige config explícito para nunca apagar o repo local)
        $targetDir = config('app.uninstall_root_path');
        if ($targetDir === null && ! app()->runningUnitTests()) {
            $targetDir = base_path();
        }

        // 3. Remove a trava de instalação e o .env imediatamente
        $installedFile = $this->getInstalledFilePath();
        if (file_exists($installedFile)) {
            @unlink($installedFile);
        }

        if (is_string($targetDir) && $targetDir !== '') {
            $envPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
            if (file_exists($envPath)) {
                @unlink($envPath);
            }
        }

        // 4. Agenda a exclusão completa da pasta do projeto para o final do ciclo de vida do PHP,
        // após a resposta HTTP já ter sido enviada ao navegador e sem depender do autoloader do Composer.
        if (is_string($targetDir) && $targetDir !== '' && is_dir($targetDir)) {
            $realTargetDir = realpath($targetDir) ?: $targetDir;

            if (app()->runningUnitTests()) {
                self::purgeInstallation($realTargetDir);
            } else {
                register_shutdown_function(static function () use ($realTargetDir) {
                    if (function_exists('fastcgi_finish_request')) {
                        @fastcgi_finish_request();
                    }

                    self::purgeInstallation($realTargetDir);
                });
            }
        }

        // Renderiza a página final estática antes que os arquivos sejam removidos no shutdown
        return response($this->renderUninstalledHtml($dbName, $dbDropped, $dbWiped), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Remove o pacote gaspar.zip (ao lado da pasta extraída) e a pasta do projeto.
     * Faz várias passadas porque o PHP/servidor pode recriar arquivos em storage/
     * (logs, sessões, views) enquanto a exclusão ainda está em andamento.
     */
    public static function purgeInstallation(string $dir): void
    {
        $zip = dirname($dir) . DIRECTORY_SEPARATOR . 'gaspar.zip';
        if (is_file($zip)) {
            @chmod($zip, 0666);
            @unlink($zip);
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::deleteDirectoryRecursively($dir);
            clearstatcache();

            if (! file_exists($dir)) {
                break;
            }

            usleep(300000);
        }
    }

    /**
     * Remove recursivamente todos os arquivos, links simbólicos e subpastas do diretório alvo,
     * além do próprio diretório ao final.
     */
    public static function deleteDirectoryRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        // Garante permissão de leitura/escrita antes de listar (pastas com permissão restrita)
        @chmod($dir, 0777);

        $items = @scandir($dir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $dir . DIRECTORY_SEPARATOR . $item;

                // Links simbólicos (ex: public/storage) devem ser removidos com unlink sem seguir o alvo
                if (is_link($path) || is_file($path)) {
                    @chmod($path, 0666);
                    @unlink($path);
                } elseif (is_dir($path)) {
                    self::deleteDirectoryRecursively($path);
                }
            }
        }

        @chmod($dir, 0777);
        @rmdir($dir);
    }

    /**
     * Gera o HTML autossuficiente exibido após a desinstalação completa.
     */
    protected function renderUninstalledHtml(?string $dbName, bool $dbDropped, bool $dbWiped): string
    {
        $dbStatusText = $dbDropped
            ? 'Banco de dados <strong>' . e($dbName ?: 'MySQL') . '</strong> excluído completamente do servidor (<code>DROP DATABASE</code>).'
            : ($dbWiped
                ? 'Todas as tabelas e dados do banco <strong>' . e($dbName ?: 'MySQL') . '</strong> foram apagados com sucesso.'
                : 'As configurações de banco de dados foram desconectadas e removidas.');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema Desinstalado — Gaspar</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0f;
            color: #f0f0f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            line-height: 1.6;
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: #16161f;
            border: 1px solid #2a2a3a;
            border-radius: 16px;
            padding: 2.25rem;
            text-align: center;
        }
        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            margin-bottom: 1.25rem;
        }
        .icon svg { width: 34px; height: 34px; fill: currentColor; }
        h1 { font-size: 1.45rem; font-weight: 700; margin-bottom: 0.5rem; color: #10b981; }
        p { color: #9090a8; font-size: 0.92rem; margin-bottom: 1.5rem; }
        .checklist {
            text-align: left;
            list-style: none;
            background: #0f0f17;
            border: 1px solid #2a2a3a;
            border-radius: 12px;
            padding: 1.25rem;
            font-size: 0.88rem;
            color: #d0d0e0;
        }
        .checklist li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #222230;
        }
        .checklist li:last-child { border-bottom: none; }
        code {
            background: rgba(255, 255, 255, 0.08);
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            font-size: 0.82rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>
        <h1>Gaspar Desinstalado com Sucesso</h1>
        <p>O sistema e todos os seus arquivos foram completamente removidos deste servidor.</p>
        <ul class="checklist">
            <li>✓ {$dbStatusText}</li>
            <li>✓ Arquivo <code>.env</code> e credenciais removidos.</li>
            <li>✓ Pasta do projeto e todos os arquivos excluídos do Gerenciador de Arquivos.</li>
        </ul>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Caminho do arquivo de trava de instalação.
     */
    protected function getInstalledFilePath(): string
    {
        return config('app.installed_file_path') ?: storage_path('app/installed.txt');
    }

    /**
     * Verifica se o sistema já está instalado.
     */
    protected function isInstalled(): bool
    {
        return file_exists($this->getInstalledFilePath());
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
            $value = (string) $value;
            // Escapa valores que contêm espaços, cerquilhas, aspas ou cifrões
            $escapedValue = $value;
            if (str_contains($value, ' ') || str_contains($value, '#') || str_contains($value, '"') || str_contains($value, '$') || $value === '') {
                $escapedValue = '"' . addcslashes($value, '"\\$') . '"';
            }

            $pattern = "/^{$key}=.*/m";
            $commentedPattern = "/^#\s*{$key}=.*/m";

            if (preg_match($pattern, $content)) {
                // Substitui valor existente via callback para proteger caracteres como $
                $content = preg_replace_callback($pattern, fn () => "{$key}={$escapedValue}", $content);
            } elseif (preg_match($commentedPattern, $content)) {
                // Descomenta e define o valor
                $content = preg_replace_callback($commentedPattern, fn () => "{$key}={$escapedValue}", $content);
            } else {
                // Adiciona ao final
                $content .= PHP_EOL . "{$key}={$escapedValue}";
            }
        }

        file_put_contents($path, $content);
    }
}
