<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_statuses', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('process_statuses', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
