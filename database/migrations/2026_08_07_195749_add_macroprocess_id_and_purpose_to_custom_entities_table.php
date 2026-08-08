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
        Schema::table('custom_entities', function (Blueprint $table) {
            $table->foreignUuid('macroprocess_id')->nullable()->after('name')->constrained('macroprocesses')->nullOnDelete();
            $table->string('purpose')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_entities', function (Blueprint $table) {
            $table->dropForeign(['macroprocess_id']);
            $table->dropColumn(['macroprocess_id', 'purpose']);
        });
    }
};
