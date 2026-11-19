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
        Schema::table('process_statuses', function (Blueprint $table) {
            $table->text('description')->nullable()->after('color');
        });

        $descriptions = [
            'new' => 'Situação inicial de toda demanda recém-aberta.',
            'closed' => 'Situação final quando o atendimento da demanda foi concluído.',
            'evaluated' => 'Situação final após o demandante avaliar a demanda concluída.',
            'canceled' => 'Situação final quando a demanda é cancelada.',
            'conditional' => 'Situação intermediária para avaliação e roteamento por regras de decisão.',
        ];

        foreach ($descriptions as $key => $text) {
            DB::table('process_statuses')
                ->where('system_key', $key)
                ->whereNull('description')
                ->update(['description' => $text, 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_statuses', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
