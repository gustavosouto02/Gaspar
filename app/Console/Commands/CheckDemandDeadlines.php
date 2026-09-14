<?php

namespace App\Console\Commands;

use App\Enums\DemandStatusEnum;
use App\Models\Demand;
use App\Notifications\DemandDeadlineNotification;
use Illuminate\Console\Command;

class CheckDemandDeadlines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-demand-deadlines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica demandas que estão vencendo hoje ou já estão vencidas e envia alertas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->startOfDay();

        // Buscamos demandas que possuem SLA, que não estão encerradas/canceladas, e que o SLA é hoje ou no passado.
        $demands = Demand::whereNotNull('sla_due_at')
            ->whereNotIn('status', [DemandStatusEnum::CLOSED->value, DemandStatusEnum::CANCELED->value])
            ->where('sla_due_at', '<=', $today->copy()->endOfDay())
            ->get();

        $count = 0;

        foreach ($demands as $demand) {
            $isOverdue = $demand->sla_due_at->startOfDay()->lt($today);
            
            // Enviamos para o responsável caso exista (vamos usar relationship assignedTo)
            if ($demand->assignedTo) {
                $demand->assignedTo->notify(new DemandDeadlineNotification($demand, $isOverdue));
                $count++;
            }
        }

        $this->info("{$count} alertas de prazo enviados.");
    }
}
