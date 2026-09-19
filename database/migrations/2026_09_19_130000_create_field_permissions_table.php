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
        Schema::create('field_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')->constrained('custom_entities')->onDelete('cascade');
            $table->foreignUuid('custom_field_id')->constrained('custom_fields')->onDelete('cascade');
            $table->foreignUuid('process_status_id')->constrained('process_statuses')->onDelete('cascade');
            // null = regra genérica, vale para todos os papéis
            $table->foreignUuid('process_role_id')->nullable()->constrained('process_roles')->onDelete('cascade');
            $table->string('permission');
            $table->timestamps();

            $table->unique(
                ['entity_id', 'custom_field_id', 'process_status_id', 'process_role_id'],
                'field_perms_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_permissions');
    }
};
