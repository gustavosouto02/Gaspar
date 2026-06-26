<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')->constrained('custom_entities')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('process_role_id')->constrained('process_roles')->onDelete('restrict');
            $table->timestamps();

            // Um usuário não pode ter o mesmo papel duas vezes no mesmo processo
            $table->unique(['entity_id', 'user_id', 'process_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_members');
    }
};
