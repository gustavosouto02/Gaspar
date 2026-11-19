<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('macroprocesses')
            ->where('value_chain_function', 'Apoio')
            ->update(['value_chain_function' => 'Suportar Processos']);
    }

    public function down(): void
    {
        // Sem volta: após a conversão não é possível distinguir registros que já eram "Suportar Processos" daqueles convertidos de "Apoio".
    }
};
