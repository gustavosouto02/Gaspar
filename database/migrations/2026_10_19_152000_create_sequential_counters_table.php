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
        if (! Schema::hasTable('sequential_counters')) {
            Schema::create('sequential_counters', function (Blueprint $table) {
                $table->id();
                $table->string('prefix', 32);
                $table->unsignedInteger('year');
                $table->unsignedBigInteger('current_sequence')->default(0);
                $table->timestamps();

                $table->unique(['prefix', 'year']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequential_counters');
    }
};
