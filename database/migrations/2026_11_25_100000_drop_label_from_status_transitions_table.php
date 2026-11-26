<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O botão de toda transição passa a ser sempre "Enviar": não há texto a guardar.
     */
    public function up(): void
    {
        Schema::table('status_transitions', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }

    public function down(): void
    {
        Schema::table('status_transitions', function (Blueprint $table) {
            $table->string('label')->default('Enviar')->after('to_status_id');
        });
    }
};
