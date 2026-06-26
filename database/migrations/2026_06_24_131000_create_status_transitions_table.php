<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Processo ao qual a transição pertence
            $table->foreignUuid('entity_id')->constrained('custom_entities')->onDelete('cascade');

            // NULL = "de qualquer situação" (ou demanda sem situação)
            $table->foreignUuid('from_status_id')
                ->nullable()
                ->constrained('process_statuses')
                ->nullOnDelete();

            // Situação de destino
            $table->foreignUuid('to_status_id')
                ->constrained('process_statuses')
                ->onDelete('cascade');

            // Texto do botão de ação (ex: "Aprovar", "Encaminhar para Análise")
            $table->string('label');

            // Papéis permitidos: [] = todos os membros podem disparar
            // array de process_role_ids (UUIDs)
            $table->json('allowed_role_ids')->nullable();

            $table->integer('display_order')->default(0);
            $table->timestamps();

            // Não faz sentido duplicar a mesma transição no mesmo processo
            $table->unique(['entity_id', 'from_status_id', 'to_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_transitions');
    }
};
