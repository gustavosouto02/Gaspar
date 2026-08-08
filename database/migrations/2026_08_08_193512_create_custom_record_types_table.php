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
        Schema::create('custom_record_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('custom_record_type_custom_field', function (Blueprint $table) {
            $table->uuid('custom_record_type_id');
            $table->uuid('custom_field_id');
            $table->integer('field_order')->default(0);
            $table->timestamps();

            $table->primary(['custom_record_type_id', 'custom_field_id'], 'crt_cf_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_record_type_custom_field');
        Schema::dropIfExists('custom_record_types');
    }
};
