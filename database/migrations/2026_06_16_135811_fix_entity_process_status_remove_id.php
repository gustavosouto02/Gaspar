<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabelas pivot no Laravel NÃO devem ter id próprio.
        // O attach() do Eloquent insere apenas as FKs + colunas pivot,
        // sem gerar um id — o que causava o erro "Field 'id' doesn't have a default value".
        Schema::drop('entity_process_status');

        Schema::create('entity_process_status', function (Blueprint $table) {
            $table->foreignUuid('entity_id')->constrained('custom_entities')->onDelete('cascade');
            $table->foreignUuid('process_status_id')->constrained('process_statuses')->onDelete('cascade');
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['entity_id', 'process_status_id']);
        });
    }

    public function down(): void
    {
        Schema::drop('entity_process_status');

        Schema::create('entity_process_status', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')->constrained('custom_entities')->onDelete('cascade');
            $table->foreignUuid('process_status_id')->constrained('process_statuses')->onDelete('cascade');
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->unique(['entity_id', 'process_status_id']);
        });
    }
};
