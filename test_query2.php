<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('name', 'like', '%Executor%')->first();
if (!$user) {
    echo "User not found\n";
    exit;
}
$userId = $user->id;

$query = \App\Models\Demand::query()
    ->where(function ($q) use ($userId) {
        $q->where('requested_by', $userId)
          ->orWhere('assigned_to', $userId)
          ->orWhereExists(function ($query) use ($userId) {
              $query->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('status_transitions')
                  ->whereColumn('status_transitions.entity_id', 'demands.entity_id')
                  ->where(function ($q2) {
                      $q2->whereColumn('status_transitions.from_status_id', 'demands.process_status_id')
                         ->orWhereNull('status_transitions.from_status_id');
                  })
                  ->where(function ($q3) use ($userId) {
                      $q3->whereNull('status_transitions.allowed_role_ids')
                         ->orWhereRaw("JSON_LENGTH(status_transitions.allowed_role_ids) = 0")
                         ->orWhereRaw("JSON_CONTAINS(status_transitions.allowed_role_ids, '\"__requester__\"') AND demands.requested_by = ?", [$userId])
                         ->orWhereRaw("JSON_CONTAINS(status_transitions.allowed_role_ids, '\"__assignee__\"') AND demands.assigned_to = ?", [$userId])
                         ->orWhereExists(function ($q4) use ($userId) {
                             $q4->select(\Illuminate\Support\Facades\DB::raw(1))
                                 ->from('process_members')
                                 ->whereColumn('process_members.entity_id', 'status_transitions.entity_id')
                                 ->where('process_members.user_id', $userId)
                                 ->whereRaw('JSON_CONTAINS(status_transitions.allowed_role_ids, JSON_QUOTE(process_members.process_role_id))');
                         });
                  });
          });
    });

echo "User: {$user->name} (ID: {$userId})\n";
echo "Demands found: " . $query->count() . "\n";
foreach ($query->get() as $demand) {
    echo "- Demand: {$demand->title} (Requested by: {$demand->requested_by}, Assigned to: {$demand->assigned_to})\n";
}
