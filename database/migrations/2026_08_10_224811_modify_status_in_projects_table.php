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
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->foreignUuid('entity_id')->nullable()->constrained('custom_entities')->nullOnDelete();
            $table->foreignUuid('process_status_id')->nullable()->constrained('process_statuses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status')->nullable();
            $table->dropForeign(['entity_id']);
            $table->dropColumn('entity_id');
            $table->dropForeign(['process_status_id']);
            $table->dropColumn('process_status_id');
        });
    }
};
