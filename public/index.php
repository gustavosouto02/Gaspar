<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// ── Gaspar: Auto-setup do .env antes do boot do Laravel ──────────────
// Quando o projeto é descomprimido "virgem" no servidor, o .env não existe.
// O Laravel precisa da APP_KEY para o EncryptCookies, que roda antes de
// qualquer middleware custom. Por isso criamos o .env aqui, antes do boot.
$envPath = __DIR__ . '/../.env';
$envExamplePath = __DIR__ . '/../.env.example';

if (! file_exists($envPath) && file_exists($envExamplePath)) {
    copy($envExamplePath, $envPath);

    // Gera a APP_KEY diretamente no arquivo (sem depender do Artisan)
    $key = 'base64:' . base64_encode(random_bytes(32));
    $content = file_get_contents($envPath);
    $content = preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . $key, $content);
    file_put_contents($envPath, $content);
}
// ─────────────────────────────────────────────────────────────────────

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
