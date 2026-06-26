<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$entity = \App\Models\CustomEntity::first();
if (!$entity) die("No entity\n");

$status = \App\Models\ProcessStatus::first();

$transition = \App\Models\StatusTransition::create([
    'entity_id' => $entity->id,
    'to_status_id' => $status->id,
    'label' => 'Test Save',
    'allowed_role_ids' => ['__requester__', 'fake-role-id'],
]);

$transition->refresh();
echo "Saved value type: " . gettype($transition->allowed_role_ids) . "\n";
echo "Saved value: " . json_encode($transition->allowed_role_ids) . "\n";

$transition->delete();
