<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_field_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('demand_id')->constrained('demands')->onDelete('cascade');
            $table->foreignUuid('custom_field_id')->constrained('custom_fields')->onDelete('cascade');
            $table->text('value')->nullable();
            $table->timestamps();

            // Um campo aparece uma única vez por demanda
            $table->unique(['demand_id', 'custom_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_field_values');
    }
};
