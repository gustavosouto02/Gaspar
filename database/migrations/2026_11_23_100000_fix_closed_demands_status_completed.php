<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Demandas que chegaram à situação Encerrada pelo fluxo foram gravadas como
     * COMPLETED (o autoAssign() sobrescrevia o CLOSED). Reconcilia o status delas
     * com a situação; idempotente, pois só toca as que ainda estão divergentes.
     */
    public function up(): void
    {
        DB::table('demands')
            ->where('status', 'COMPLETED')
            ->whereIn('process_status_id', DB::table('process_statuses')->where('system_key', 'closed')->select('id'))
            ->update(['status' => 'CLOSED']);
    }

    public function down(): void
    {
        // Sem volta: depois da correção não dá para distinguir as demandas que já eram CLOSED das reconciliadas.
    }
};
