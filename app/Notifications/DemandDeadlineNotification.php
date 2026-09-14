<?php

namespace App\Notifications;

use App\Filament\Resources\DemandResource;
use App\Models\Demand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemandDeadlineNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Demand $demand,
        public bool $isOverdue
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
        $statusText = $this->isOverdue ? 'está VENCIDA' : 'vence HOJE';
        $subject = $this->isOverdue ? '⚠️ Demanda Vencida: ' : '⏳ Demanda Vencendo Hoje: ';
        
        return (new MailMessage)
            ->subject($subject . '#' . $shortId . ' - ' . $this->demand->title)
            ->greeting('Olá, ' . explode(' ', $notifiable->name)[0] . '!')
            ->line('A demanda **' . $this->demand->title . '** (#' . $shortId . ') ' . $statusText . '.')
            ->line('Data do Prazo (SLA): ' . ($this->demand->sla_due_at ? $this->demand->sla_due_at->format('d/m/Y H:i') : 'N/A'))
            ->action('Abrir Demanda no Sistema', $demandUrl)
            ->line('Link direto para acesso: ' . $demandUrl)
            ->line('Por favor, verifique o status da demanda o mais rápido possível.');
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

