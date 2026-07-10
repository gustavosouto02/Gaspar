<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Quem fez
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            // O que aconteceu
            $table->string('event');                    // created | updated | deleted
            $table->string('auditable_type');           // e.g. "App\Models\Demand"
            $table->string('auditable_id');             // UUID do registro

            // O quê mudou
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Contexto técnico
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
