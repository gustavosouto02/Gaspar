<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campos de pesquisa de satisfação na demanda
        Schema::table('demands', function (Blueprint $table) {
            $table->string('satisfaction_rating')->nullable()->after('completed_at');
            $table->text('satisfaction_comment')->nullable()->after('satisfaction_rating');
        });

        // Prazo de atendimento em horas no processo
        Schema::table('custom_entities', function (Blueprint $table) {
            $table->integer('sla_hours')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table) {
            $table->dropColumn(['satisfaction_rating', 'satisfaction_comment']);
        });

        Schema::table('custom_entities', function (Blueprint $table) {
            $table->dropColumn('sla_hours');
        });
    }
};
