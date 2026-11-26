<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Padroniza os rótulos de botão existentes na tabela status_transitions para Salvar ou Enviar.
     */
    public function up(): void
    {
        DB::table('status_transitions')
            ->whereNotIn('label', ['Salvar', 'Enviar'])
            ->get()
            ->each(function ($transition) {
                $newLabel = stripos($transition->label, 'enviar') !== false
                    ? 'Enviar'
                    : 'Salvar';

                DB::table('status_transitions')
                    ->where('id', $transition->id)
                    ->update(['label' => $newLabel]);
            });
    }

    public function down(): void
    {
        // Migração de padronização irreversível para rótulos legados
    }
};
