<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona a sigla do projeto, exibida antes do nome.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('acronym', 20)->nullable()->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('acronym');
        });
    }
};
