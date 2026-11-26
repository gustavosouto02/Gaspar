<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A suíte não depende dos arquivos locais não versionados (.env e
     * storage/app/installed.txt) nem toca neles: .env, storage e trava de
     * instalação ficam numa pasta temporária própria deste checkout.
     */
    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $root = sys_get_temp_dir().'/gaspar-tests-'.md5(__DIR__);
        $storage = $root.'/storage';

        foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/testing', 'framework/views', 'logs'] as $dir) {
            if (! is_dir("{$storage}/{$dir}")) {
                mkdir("{$storage}/{$dir}", 0777, true);
            }
        }

        // .env vazio (as variáveis de teste vêm do phpunit.xml) e sistema marcado como instalado
        file_put_contents("{$root}/.env", '');
        file_put_contents("{$storage}/app/installed.txt", 'testes');

        $app->useEnvironmentPath($root);
        $app->useStoragePath($storage);

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
