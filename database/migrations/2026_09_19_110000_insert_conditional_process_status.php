<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existing = DB::table('process_statuses')->where('system_key', 'conditional')->first()
            ?? DB::table('process_statuses')->where('name', 'Condicional')->where('is_system', true)->first();

        if (! $existing) {
            $statusId = (string) Str::uuid7();

            DB::table('process_statuses')->insert([
                'id'         => $statusId,
                'name'       => 'Condicional',
                'color'      => 'yellow',
                'is_system'  => true,
                'system_key' => 'conditional',
                'created_by' => DB::table('users')->first()->id ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $statusId = $existing->id;

            DB::table('process_statuses')->where('id', $statusId)->update([
                'color'      => 'yellow',
                'is_system'  => true,
                'system_key' => 'conditional',
                'updated_at' => now(),
            ]);
        }

        // Vincula a Condicional a todos os processos existentes onde ainda não existir.
        // Processos novos recebem automaticamente via hook CustomEntity::created.
        $entityIds = DB::table('custom_entities')->pluck('id');

        foreach ($entityIds as $entityId) {
            $exists = DB::table('entity_process_status')
                ->where('entity_id', $entityId)
                ->where('process_status_id', $statusId)
                ->exists();

            if (! $exists) {
                DB::table('entity_process_status')->insert([
                    'entity_id'         => $entityId,
                    'process_status_id' => $statusId,
                    'display_order'     => 999,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $statusId = DB::table('process_statuses')->where('system_key', 'conditional')->value('id');

        if ($statusId) {
            DB::table('entity_process_status')->where('process_status_id', $statusId)->delete();
            DB::table('process_statuses')->where('id', $statusId)->delete();
        }
    }
};
