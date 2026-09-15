<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('process_statuses') && Schema::hasColumn('process_statuses', 'created_by')) {
            try {
                Schema::table('process_statuses', function (Blueprint $table) {
                    $table->uuid('created_by')->nullable()->change();
                });
            } catch (\Throwable $t) {
                // Ignora se já for anulável
            }
        }

        $adminId = DB::table('users')->first()->id ?? null;

        $statuses = [
            ['id' => (string) Str::uuid7(), 'name' => 'Nova', 'color' => 'gray', 'is_system' => true, 'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid7(), 'name' => 'Encerrada', 'color' => 'blue', 'is_system' => true, 'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid7(), 'name' => 'Avaliada', 'color' => 'green', 'is_system' => true, 'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid7(), 'name' => 'Cancelada', 'color' => 'red', 'is_system' => true, 'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($statuses as $status) {
            $existing = DB::table('process_statuses')->where('name', $status['name'])->where('is_system', true)->first();
            if (! $existing) {
                DB::table('process_statuses')->insert($status);
            } else {
                DB::table('process_statuses')
                    ->where('id', $existing->id)
                    ->update([
                        'color' => $status['color'],
                        'updated_at' => $status['updated_at']
                    ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('process_statuses')->where('is_system', true)->whereIn('name', ['Nova', 'Encerrada', 'Avaliada', 'Cancelada'])->delete();
    }
};
