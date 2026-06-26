<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_process_status', function (Blueprint $table) {
            $table->foreignUuid('entity_id')->constrained('custom_entities')->onDelete('cascade');
            $table->foreignUuid('process_status_id')->constrained('process_statuses')->onDelete('cascade');
            $table->integer('display_order')->default(0);
            $table->timestamps();

            // Uma situação não pode ser vinculada duas vezes ao mesmo processo
            $table->unique(['entity_id', 'process_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_process_status');
    }
};
