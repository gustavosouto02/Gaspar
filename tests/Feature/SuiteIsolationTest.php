<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Os testes não leem, apagam nem sobrescrevem o .env e a trava de instalação do desenvolvedor.
 */
class SuiteIsolationTest extends TestCase
{
    public function test_env_e_storage_dos_testes_ficam_fora_do_projeto(): void
    {
        $this->assertStringStartsNotWith(base_path(), $this->app->environmentFilePath());
        $this->assertStringStartsNotWith(base_path(), storage_path());
        $this->assertFileExists($this->app->environmentFilePath());
        $this->assertFileExists(storage_path('app/installed.txt'));
    }

    public function test_sem_a_trava_o_sistema_continua_redirecionando_para_o_instalador(): void
    {
        // Trava temporária dos testes: é recriada no próximo teste
        unlink(storage_path('app/installed.txt'));

        // Rota do grupo "web", onde o EnsureInstalled atua
        $this->get('/desinstalar')->assertRedirect(route('instalar'));
    }
}
