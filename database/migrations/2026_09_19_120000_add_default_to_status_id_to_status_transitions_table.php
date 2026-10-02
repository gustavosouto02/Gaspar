<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('status_transitions', function (Blueprint $table) {
            // Situação "senão" da tabela de decisão (só usada quando o destino é a Condicional)
            $table->foreignUuid('default_to_status_id')
                ->nullable()
                ->after('to_status_id')
                ->constrained('process_statuses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('status_transitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_to_status_id');
        });
    }
};
