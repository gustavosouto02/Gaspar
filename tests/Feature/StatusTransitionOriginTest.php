<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomEntityResource\Pages\EditCustomEntity;
use App\Filament\Resources\CustomEntityResource\RelationManagers\StatusTransitionsRelationManager;
use App\Models\CustomEntity;
use App\Models\Macroprocess;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class StatusTransitionOriginTest extends TestCase
{
    use RefreshDatabase;

    private CustomEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->actingAs($admin);

        $macro = Macroprocess::create([
            'name' => 'Macro', 'acronym' => 'MC', 'value_chain_function' => 'Suportar Processos',
            'description' => 'Desc', 'is_active' => true, 'created_by' => $admin->id,
        ]);

        $this->entity = CustomEntity::create([
            'name' => 'Processo', 'macroprocess_id' => $macro->id, 'purpose' => 'Apoio',
            'sla_hours' => 24, 'description' => 'Desc', 'is_active' => true, 'created_by' => $admin->id,
        ]);
    }

    private function transitions(): Testable
    {
        return Livewire::test(StatusTransitionsRelationManager::class, [
            'ownerRecord' => $this->entity,
            'pageClass' => EditCustomEntity::class,
        ]);
    }

    public function test_nova_transicao_ja_vem_com_a_situacao_nova_como_origem(): void
    {
        $this->transitions()
            ->mountTableAction('create')
            ->assertTableActionDataSet(['from_status_id' => ProcessStatus::findBySystemKey('new')->id])
            ->assertDontSee('Qualquer situação (inicial)');
    }

    public function test_origem_da_transicao_eh_obrigatoria(): void
    {
        $this->transitions()
            ->callTableAction('create', data: [
                'from_status_id' => null,
                'to_status_id' => ProcessStatus::findBySystemKey('closed')->id,
                'allowed_role_ids' => ['__requester__'],
            ])
            ->assertHasTableActionErrors(['from_status_id' => 'required']);

        $this->assertSame(0, StatusTransition::where('entity_id', $this->entity->id)->count());
    }

    public function test_transicao_para_a_propria_situacao_de_origem_eh_recusada(): void
    {
        $new = ProcessStatus::findBySystemKey('new')->id;

        $this->transitions()
            ->callTableAction('create', data: [
                'from_status_id' => $new,
                'to_status_id' => $new,
                'allowed_role_ids' => ['__requester__'],
            ])
            ->assertHasTableActionErrors(['to_status_id' => 'different']);

        $this->assertSame(0, StatusTransition::where('entity_id', $this->entity->id)->count());
    }

    public function test_editar_uma_transicao_salva_sem_erro(): void
    {
        $transition = StatusTransition::create([
            'entity_id' => $this->entity->id,
            'from_status_id' => ProcessStatus::findBySystemKey('new')->id,
            'to_status_id' => ProcessStatus::findBySystemKey('closed')->id,
            'allowed_role_ids' => ['__requester__'],
        ]);

        $this->transitions()
            ->mountTableAction('edit', $transition)
            ->assertFormFieldDoesNotExist('label', 'mountedTableActionForm')
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();
    }

    public function test_situacao_de_origem_aceita_uma_unica_transicao(): void
    {
        $new = ProcessStatus::findBySystemKey('new')->id;
        $data = [
            'from_status_id' => $new,
            'to_status_id' => ProcessStatus::findBySystemKey('closed')->id,
            'allowed_role_ids' => ['__requester__'],
        ];

        $this->transitions()->callTableAction('create', data: $data)->assertHasNoTableActionErrors();
        $this->transitions()->callTableAction('create', data: $data)
            ->assertHasTableActionErrors(['from_status_id' => 'unique']);

        $this->assertSame(1, StatusTransition::where('entity_id', $this->entity->id)->count());
    }

    public function test_destino_nao_oferece_a_situacao_de_origem(): void
    {
        $new = ProcessStatus::findBySystemKey('new')->id;
        $closed = ProcessStatus::findBySystemKey('closed')->id;

        $this->transitions()
            ->mountTableAction('create')
            ->assertFormFieldExists('to_status_id', 'mountedTableActionForm', function (Select $field) use ($new, $closed): bool {
                $options = $field->getOptions();

                return ! isset($options[$new]) && isset($options[$closed]);
            });
    }
}
