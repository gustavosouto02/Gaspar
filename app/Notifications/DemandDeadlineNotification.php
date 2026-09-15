<?php

namespace App\Notifications;

use App\Filament\Resources\DemandResource;
use App\Models\Demand;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemandDeadlineNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Demand $demand,
        public string $type = 'today', // 'tomorrow', 'today', 'overdue'
        public int $daysOverdue = 0
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $demandUrl = DemandResource::getUrl('edit', ['record' => $this->demand]);
        $shortId = strtoupper(substr($this->demand->id, 0, 8));
        $slaFormatted = $this->demand->sla_due_at ? $this->demand->sla_due_at->format('d/m/Y H:i') : 'Data não definida';

        $mailMessage = new MailMessage();

        switch ($this->type) {
            case 'tomorrow':
                $mailMessage
                    ->subject("⏳ Demanda vencendo AMANHÃ: #{$shortId} - {$this->demand->title}")
                    ->greeting('Olá, ' . explode(' ', $notifiable->name)[0] . '!')
                    ->line("A demanda **{$this->demand->title}** (#{$shortId}) vencerá **amanhã**.")
                    ->line("**Data limite (SLA):** {$slaFormatted}")
                    ->line('Este é um lembrete preventivo para que as atividades possam ser finalizadas dentro do prazo planejado.')
                    ->action('Abrir Demanda no Sistema', $demandUrl)
                    ->line('Link direto para acesso: ' . $demandUrl);
                break;

            case 'today':
                $mailMessage
                    ->subject("⏳ Atenção: Demanda vence HOJE: #{$shortId} - {$this->demand->title}")
                    ->greeting('Olá, ' . explode(' ', $notifiable->name)[0] . '!')
                    ->line("A demanda **{$this->demand->title}** (#{$shortId}) **vence HOJE**.")
                    ->line("**Data limite (SLA):** {$slaFormatted}")
                    ->line('Por favor, priorize o atendimento para evitar atrasos no cumprimento do SLA.')
                    ->action('Abrir Demanda no Sistema', $demandUrl)
                    ->line('Link direto para acesso: ' . $demandUrl);
                break;

            case 'overdue':
            default:
                $daysText = $this->daysOverdue > 1 ? "há {$this->daysOverdue} dias" : 'há 1 dia';
                $mailMessage
                    ->subject("⚠️ Demanda VENCIDA ({$daysText}): #{$shortId} - {$this->demand->title}")
                    ->greeting('Olá, ' . explode(' ', $notifiable->name)[0] . '!')
                    ->line("A demanda **{$this->demand->title}** (#{$shortId}) está **VENCIDA** {$daysText}.")
                    ->line("**Data de vencimento original:** {$slaFormatted}")
                    ->line('Ela continua aberta e pendente no sistema. Solicitamos atenção para concluir o atendimento ou atualizar a situação com urgência.')
                    ->action('Abrir Demanda no Sistema', $demandUrl)
                    ->line('Link direto para acesso: ' . $demandUrl);
                break;
        }

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}

