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
        Schema::create('transition_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('status_transition_id')->constrained('status_transitions')->onDelete('cascade');
            $table->integer('rule_order')->default(0);
            $table->json('conditions');
            $table->foreignUuid('to_status_id')->constrained('process_statuses')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transition_rules');
    }
};
