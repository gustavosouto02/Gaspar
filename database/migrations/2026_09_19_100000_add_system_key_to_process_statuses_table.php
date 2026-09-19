<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('process_statuses', 'system_key')) {
            Schema::table('process_statuses', function (Blueprint $table) {
                $table->string('system_key')->nullable()->unique()->after('is_system');
            });
        }

        $map = [
            'Nova'      => 'new',
            'Encerrada' => 'closed',
            'Avaliada'  => 'evaluated',
            'Cancelada' => 'canceled',
        ];

        foreach ($map as $name => $key) {
            DB::table('process_statuses')
                ->where('name', $name)
                ->where('is_system', true)
                ->whereNull('system_key')
                ->update(['system_key' => $key, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('process_statuses', 'system_key')) {
            Schema::table('process_statuses', function (Blueprint $table) {
                try {
                    $table->dropUnique(['system_key']);
                } catch (\Throwable $t) {
                    // Ignora se o índice não existir (ex.: SQLite recria a tabela)
                }
                $table->dropColumn('system_key');
            });
        }
    }
};
