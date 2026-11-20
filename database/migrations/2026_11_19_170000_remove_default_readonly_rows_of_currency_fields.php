<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A grade de permissões gravava todas as células, inclusive o padrão "Somente
     * leitura". Para campos de valor (CURRENCY) uma linha explícita diferente de
     * Oculto libera o campo a quem não é gestor; as linhas READONLY antigas são só
     * o padrão gravado e, mantidas, liberariam o valor a todos. Removê-las não muda
     * o comportamento anterior (sem linha vale o mesmo padrão READONLY).
     */
    public function up(): void
    {
        DB::table('field_permissions')
            ->where('permission', 'READONLY')
            ->whereIn('custom_field_id', DB::table('custom_fields')->where('field_type', 'CURRENCY')->select('id'))
            ->delete();
    }

    public function down(): void
    {
        // Sem volta: as linhas removidas eram apenas o padrão gravado.
    }
};
