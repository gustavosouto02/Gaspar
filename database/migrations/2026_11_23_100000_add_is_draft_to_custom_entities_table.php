<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_entities', function (Blueprint $table) {
            // Versão em elaboração: não é vigente e ainda não substituiu nenhuma versão.
            // Só deixa de ser rascunho quando o admin publica.
            $table->boolean('is_draft')->default(false)->index()->after('is_current');
        });
    }

    public function down(): void
    {
        Schema::table('custom_entities', function (Blueprint $table) {
            $table->dropIndex(['is_draft']);
            $table->dropColumn('is_draft');
        });
    }
};
