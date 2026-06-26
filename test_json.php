<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$transition = \App\Models\StatusTransition::whereNotNull('allowed_role_ids')->first();
if ($transition) {
    echo "Transition ID: {$transition->id}\n";
    echo "Allowed Roles (raw): " . json_encode($transition->getAttributes()['allowed_role_ids']) . "\n";
    $roleId = $transition->allowed_role_ids[0] ?? null;
    if ($roleId) {
        $result = \Illuminate\Support\Facades\DB::selectOne('SELECT JSON_CONTAINS(allowed_role_ids, JSON_QUOTE(?)) as has_role FROM status_transitions WHERE id = ?', [$roleId, $transition->id]);
        echo "JSON_CONTAINS result: {$result->has_role}\n";
    }
} else {
    echo "No transition with allowed_role_ids found.\n";
}
