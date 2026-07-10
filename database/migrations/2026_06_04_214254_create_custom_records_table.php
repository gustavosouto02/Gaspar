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
        Schema::create('custom_records', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID7
            $table->foreignUuid('entity_id')->constrained('custom_entities')->onDelete('cascade');
            $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');
            $table->json('data_json');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_records');
    }
};
