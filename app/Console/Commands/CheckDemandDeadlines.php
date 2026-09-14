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
    protected $signature = 'app:check-demand-deadlines {--force : Ignora a verificação de envio diário e reenvia alertas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emite notificações de prazo: 1 dia antes de vencer, no dia do vencimento e 1x ao dia enquanto estiver vencida';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->startOfDay();
        $todayDate = $today->toDateString();
        $isForce = (bool) $this->option('force');

        // Busca demandas ativas com data de SLA definida
        $demands = Demand::whereNotNull('sla_due_at')
            ->whereNotIn('status', [
                DemandStatusEnum::CLOSED->value,
                DemandStatusEnum::CANCELED->value,
                DemandStatusEnum::COMPLETED->value,
                DemandStatusEnum::EVALUATED->value,
                DemandStatusEnum::DRAFT->value,
            ])
            ->get();

        $tomorrowCount = 0;
        $todayCount = 0;
        $overdueCount = 0;

        foreach ($demands as $demand) {
            // Se já foi alertado hoje e não está forçando reenvio, pula
            if (! $isForce && $demand->last_deadline_alert_date && $demand->last_deadline_alert_date->toDateString() === $todayDate) {
                continue;
            }

            $dueDay = $demand->sla_due_at->copy()->startOfDay();
            $diffInDays = (int) $today->diffInDays($dueDay, false);

            $type = null;
            $daysOverdue = 0;

            if ($diffInDays === 1) {
                // 1 dia antes de vencer
                $type = 'tomorrow';
                $tomorrowCount++;
            } elseif ($diffInDays === 0) {
                // No dia do vencimento
                $type = 'today';
                $todayCount++;
            } elseif ($diffInDays < 0) {
                // Já está vencida (uma vez por dia após vencida)
                $type = 'overdue';
                $daysOverdue = abs($diffInDays);
                $overdueCount++;
            } else {
                // Faltam 2 ou mais dias para o prazo, não notifica ainda
                continue;
            }

            // Destinatário prioritário é o responsável atribuído; se não houver, notifica o solicitante
            $recipient = $demand->assignedTo ?? $demand->requestedBy;

            if ($recipient) {
                $recipient->notify(new DemandDeadlineNotification($demand, $type, $daysOverdue));
                $demand->updateQuietly(['last_deadline_alert_date' => $todayDate]);
            }
        }

        $total = $tomorrowCount + $todayCount + $overdueCount;
        $this->info("Verificação de prazos concluída:");
        $this->line("- Vencendo amanhã: {$tomorrowCount}");
        $this->line("- Vencendo hoje: {$todayCount}");
        $this->line("- Vencidas (diário): {$overdueCount}");
        $this->info("Total de alertas enviados: {$total}");

        return Command::SUCCESS;
    }
}

