<?php

namespace App\Notifications;

use App\Filament\Resources\DemandResource;
use App\Models\Demand;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemandSatisfactionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Demand $demand
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
        $demandUrl = DemandResource::getUrl('view', ['record' => $this->demand]);
        $shortId = strtoupper(substr($this->demand->id, 0, 8));
        
        return (new MailMessage)
            ->subject('Pesquisa de Satisfação: Demanda #' . $shortId . ' - ' . $this->demand->title)
            ->greeting('Olá, ' . explode(' ', $notifiable->name)[0] . '!')
            ->line('A demanda **' . $this->demand->title . '** (#' . $shortId . ') foi concluída.')
            ->line('Gostaríamos muito de saber como foi a sua experiência com o atendimento.')
            ->action('Avaliar Atendimento', $demandUrl)
            ->line('Link direto para acesso: ' . $demandUrl)
            ->line('Leva apenas um minuto e nos ajuda a melhorar cada vez mais!');
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

