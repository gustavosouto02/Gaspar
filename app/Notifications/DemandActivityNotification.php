<?php

namespace App\Notifications;

use App\Filament\Resources\DemandResource;
use App\Models\Demand;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemandActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Demand $demand,
        public string $activityMessage
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

        return (new MailMessage)
            ->subject('Atualização na Demanda #' . $shortId . ' - ' . $this->demand->title)
            ->greeting('Olá, ' . explode(' ', $notifiable->name)[0] . '!')
            ->line('Houve uma nova atividade na demanda: **' . $this->demand->title . '** (#' . $shortId . ')')
            ->line('**Detalhe:** ' . $this->activityMessage)
            ->action('Abrir Demanda no Sistema', $demandUrl)
            ->line('Link direto para acesso: ' . $demandUrl)
            ->line('Obrigado por usar o sistema Gaspar!');
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

