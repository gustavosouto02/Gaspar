<?php

namespace Tests\Feature;

use App\Filament\Resources\DemandResource;
use App\Models\Demand;
use App\Models\User;
use App\Notifications\DemandActivityNotification;
use App\Notifications\DemandDeadlineNotification;
use App\Notifications\DemandSatisfactionNotification;
use Database\Seeders\AquiProcessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Demand $demand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AquiProcessSeeder::class);

        $this->user = User::firstOrFail();
        $this->demand = Demand::firstOrFail();
    }

    public function test_demand_activity_notification_has_acessar_demanda_and_linked_short_id(): void
    {
        $notification = new DemandActivityNotification($this->demand, 'Atividade de teste');
        $mail = $notification->toMail($this->user);

        $code = $this->demand->code ?: '#' . strtoupper(substr($this->demand->id, 0, 8));
        $demandUrl = DemandResource::getUrl('edit', ['record' => $this->demand]);

        $this->assertSame('Acessar demanda', $mail->actionText);
        $this->assertSame($demandUrl, $mail->actionUrl);
        $this->assertContains("Houve uma nova atividade na demanda: **{$this->demand->title}** ([{$code}]({$demandUrl}))", $mail->introLines);
    }

    public function test_demand_deadline_notifications_have_acessar_demanda_and_linked_short_id(): void
    {
        $code = $this->demand->code ?: '#' . strtoupper(substr($this->demand->id, 0, 8));
        $demandUrl = DemandResource::getUrl('edit', ['record' => $this->demand]);

        foreach (['tomorrow', 'today', 'overdue'] as $type) {
            $notification = new DemandDeadlineNotification($this->demand, $type, 3);
            $mail = $notification->toMail($this->user);

            $this->assertSame('Acessar demanda', $mail->actionText);
            $this->assertSame($demandUrl, $mail->actionUrl);

            $expectedSubstring = "[{$code}]({$demandUrl})";
            $firstLine = $mail->introLines[0];
            $this->assertStringContainsString($expectedSubstring, $firstLine);
        }
    }

    public function test_demand_satisfaction_notification_has_linked_short_id(): void
    {
        $notification = new DemandSatisfactionNotification($this->demand);
        $mail = $notification->toMail($this->user);

        $code = $this->demand->code ?: '#' . strtoupper(substr($this->demand->id, 0, 8));
        $demandUrl = DemandResource::getUrl('view', ['record' => $this->demand]);

        $this->assertSame('Avaliar Atendimento', $mail->actionText);
        $this->assertSame($demandUrl, $mail->actionUrl);
        $this->assertContains("A demanda **{$this->demand->title}** ([{$code}]({$demandUrl})) foi concluída.", $mail->introLines);
    }
}
