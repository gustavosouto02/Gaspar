<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('demand_id')->constrained('demands')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('restrict');
            $table->text('comment');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_comments');
    }
};
