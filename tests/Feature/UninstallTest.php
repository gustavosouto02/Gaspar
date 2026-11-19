<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UninstallTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    private string $tempProjectDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Simula o cPanel: <raiz>/gaspar.zip ao lado da pasta extraída <raiz>/gaspar
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gaspar_uninstall_test_' . uniqid();
        $this->tempProjectDir = $this->tempRoot . '/gaspar';
        mkdir($this->tempProjectDir, 0777, true);
        file_put_contents($this->tempRoot . '/gaspar.zip', 'zip');
        mkdir($this->tempProjectDir . '/storage/framework/views', 0777, true);
        file_put_contents($this->tempProjectDir . '/storage/framework/views/a.php', 'x');
        mkdir($this->tempProjectDir . '/app/Http', 0777, true);
        mkdir($this->tempProjectDir . '/storage/app', 0777, true);
        mkdir($this->tempProjectDir . '/public', 0777, true);

        file_put_contents($this->tempProjectDir . '/.env', "APP_NAME=Gaspar\nDB_DATABASE=gaspar_test\n");
        file_put_contents($this->tempProjectDir . '/.htaccess', "RewriteEngine On\n");
        file_put_contents($this->tempProjectDir . '/app/Http/Controller.php', "<?php // dummy\n");
        file_put_contents($this->tempProjectDir . '/storage/app/installed.txt', now()->toIso8601String());

        config([
            'app.uninstall_root_path' => $this->tempProjectDir,
            'app.installed_file_path' => $this->tempProjectDir . '/storage/app/installed.txt',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempRoot)) {
            \App\Http\Controllers\InstaladorController::deleteDirectoryRecursively($this->tempRoot);
        }

        parent::tearDown();
    }

    public function test_non_admin_cannot_execute_uninstall(): void
    {
        $user = User::factory()->create([
            'user_role' => UserRoleEnum::VIEWER,
        ]);

        $response = $this->actingAs($user)->post('/desinstalar');

        $response->assertStatus(403);
        $this->assertDirectoryExists($this->tempProjectDir);
        $this->assertFileExists($this->tempProjectDir . '/.env');
    }

    public function test_admin_uninstall_wipes_database_and_deletes_entire_project_directory(): void
    {
        $admin = User::factory()->create([
            'user_role' => UserRoleEnum::ADMIN,
        ]);

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertDirectoryExists($this->tempProjectDir);

        $response = $this->actingAs($admin)->post('/desinstalar');

        $response->assertStatus(200);
        $response->assertSee('Gaspar Desinstalado com Sucesso');
        $response->assertSee('Pasta do projeto e todos os arquivos excluídos do Gerenciador de Arquivos.');

        // Verifica que todas as tabelas foram apagadas (db:wipe)
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('demands'));

        // Verifica que a pasta inteira do projeto e seus arquivos (.env, .htaccess, subpastas) foram apagados
        $this->assertDirectoryDoesNotExist($this->tempProjectDir);

        // O gaspar.zip enviado ao servidor também é removido
        $this->assertFileDoesNotExist($this->tempRoot . '/gaspar.zip');
    }
}
