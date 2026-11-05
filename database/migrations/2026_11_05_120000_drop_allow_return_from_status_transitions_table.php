<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('status_transitions', 'allow_return')) {
            Schema::table('status_transitions', function (Blueprint $table) {
                $table->dropColumn('allow_return');
            });
        }
    }

    public function down(): void
    {
        Schema::table('status_transitions', function (Blueprint $table) {
            $table->boolean('allow_return')->default(false)->after('label');
        });
    }
};
