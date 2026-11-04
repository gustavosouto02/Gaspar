<?php

namespace Tests\Feature;

use App\Enums\DemandStatusEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Resources\DemandResource\Pages\ViewDemand;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\Macroprocess;
use App\Models\ProcessMember;
use App\Models\ProcessRole;
use App\Models\ProcessStatus;
use App\Models\User;
use App\Notifications\DemandActivityNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class DemandCancelNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $requester;
    private User $assignee;
    private User $member;
    private Demand $demand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->requester = User::factory()->create(['user_role' => UserRoleEnum::EXECUTOR, 'is_active' => true]);
        $this->assignee = User::factory()->create(['user_role' => UserRoleEnum::EXECUTOR, 'is_active' => true]);
        $this->member = User::factory()->create(['user_role' => UserRoleEnum::EXECUTOR, 'is_active' => true]);

        $macro = Macroprocess::create(['name' => 'Macro', 'acronym' => 'MAC', 'is_active' => true, 'created_by' => $this->admin->id]);
        $entity = CustomEntity::create(['name' => 'Processo', 'macroprocess_id' => $macro->id, 'is_active' => true, 'created_by' => $this->admin->id]);
        $client = Client::create(['name' => 'Cliente', 'is_active' => true]);

        $role = ProcessRole::factory()->create(['created_by' => $this->admin->id]);
        ProcessMember::create(['entity_id' => $entity->id, 'user_id' => $this->member->id, 'process_role_id' => $role->id]);
        ProcessMember::create(['entity_id' => $entity->id, 'user_id' => $this->assignee->id, 'process_role_id' => $role->id]);

        $this->demand = Demand::create([
            'title' => 'Demanda a cancelar',
            'entity_id' => $entity->id,
            'process_status_id' => ProcessStatus::findBySystemKey('new')->id,
            'client_id' => $client->id,
            'requested_by' => $this->requester->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->requester->id,
            'status' => DemandStatusEnum::ACTIVE,
        ]);
    }

    private function cancelAs(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getDefaultPanel());

        Livewire::test(ViewDemand::class, ['record' => $this->demand->getKey()])
            ->callAction('cancelar');
    }

    public function test_admin_cancel_notifies_everyone_except_actor(): void
    {
        Notification::fake();
        $this->cancelAs($this->admin);

        Notification::assertSentTo([$this->requester, $this->assignee, $this->member], DemandActivityNotification::class);
        Notification::assertNotSentTo($this->admin, DemandActivityNotification::class);
    }

    public function test_requester_cancel_notifies_assignee_and_members_but_not_self(): void
    {
        Notification::fake();
        $this->cancelAs($this->requester);

        Notification::assertSentTo([$this->assignee, $this->member], DemandActivityNotification::class);
        Notification::assertNotSentTo($this->requester, DemandActivityNotification::class);
        Notification::assertSentToTimes($this->assignee, DemandActivityNotification::class, 1);
    }
}
