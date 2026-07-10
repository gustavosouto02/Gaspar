<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demands', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Processo (entidade dinâmica)
            $table->foreignUuid('entity_id')->constrained('custom_entities')->onDelete('restrict');

            // Situação atual do processo
            $table->foreignUuid('process_status_id')->nullable()->constrained('process_statuses')->nullOnDelete();

            // Cliente e Projeto
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();

            // Pessoas
            $table->foreignUuid('requested_by')->constrained('users')->onDelete('restrict');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');

            // Subdemanda
            $table->foreignUuid('parent_demand_id')->nullable()->constrained('demands')->nullOnDelete();

            // Conteúdo
            $table->string('title');
            $table->text('description')->nullable();

            // Status e prioridade
            $table->string('status')->default('ACTIVE'); // DemandStatusEnum
            $table->string('priority')->default('MEDIUM'); // DemandPriorityEnum

            // Datas de controle
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demands');
    }
};
