<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$demand = \App\Models\Demand::first();
if ($demand) {
    echo "Demand ID: {$demand->id}\n";
    echo "Current Responsibles: " . $demand->current_responsibles . "\n";
} else {
    echo "No demand found.\n";
}
