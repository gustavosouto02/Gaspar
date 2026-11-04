<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('sponsor_rating')->nullable()->after('spent');
            $table->text('sponsor_comment')->nullable()->after('sponsor_rating');
        });

        // O texto livre antigo vira o comentário da avaliação
        DB::table('projects')
            ->whereNotNull('sponsor_evaluation')
            ->update(['sponsor_comment' => DB::raw('sponsor_evaluation')]);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('sponsor_evaluation');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('sponsor_evaluation')->nullable()->after('spent');
        });

        DB::table('projects')->update(['sponsor_evaluation' => DB::raw('sponsor_comment')]);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['sponsor_rating', 'sponsor_comment']);
        });
    }
};
