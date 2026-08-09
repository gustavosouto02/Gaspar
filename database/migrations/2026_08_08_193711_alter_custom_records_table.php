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
        Schema::table('custom_records', function (Blueprint $table) {
            $table->dropForeign('custom_records_entity_id_foreign');
            $table->dropColumn('entity_id');
            $table->uuid('custom_record_type_id')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_records', function (Blueprint $table) {
            $table->dropColumn('custom_record_type_id');
            $table->uuid('entity_id')->nullable()->after('id');
        });
    }
};
