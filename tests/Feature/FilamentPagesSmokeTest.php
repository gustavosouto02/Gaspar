<?php

namespace Tests\Feature;

use App\Enums\DemandButtonEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Pages\ControlPanel;
use App\Filament\Pages\DemandReports;
use App\Filament\Pages\MyDemands;
use App\Filament\Resources\DemandResource\Pages\CreateDemand;
use App\Filament\Resources\DemandResource\Pages\EditDemand;
use App\Filament\Resources\DemandResource\Pages\ListDemands;
use App\Filament\Resources\DemandResource\Pages\ViewDemand;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Widgets\DemandByProcessChart;
use App\Filament\Widgets\DemandSLAChart;
use App\Filament\Widgets\DemandStatsWidget;
use App\Filament\Widgets\GeneralStatsOverview;
use App\Filament\Widgets\MyDemandsWidget;
use App\Filament\Widgets\MySubDemandsWidget;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\Macroprocess;
use App\Models\ProcessStatus;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Garante que as telas Filament de Demanda e Projeto renderizam sem erro.
 *
 * Esses testes existem para pegar falhas de runtime (ex.: "Class not found" por
 * import faltante) que os testes de serviço/model não exercitam.
 */
class FilamentPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CustomEntity $entity;

    private Demand $demand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'user_role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $macro = Macroprocess::create([
            'name' => 'Macro Smoke',
            'acronym' => 'SMK',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->entity = CustomEntity::create([
            'name' => 'Processo Smoke',
            'macroprocess_id' => $macro->id,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $client = Client::create(['name' => 'Cliente Smoke', 'is_active' => true]);

        $new = ProcessStatus::findBySystemKey('new');
        $closed = ProcessStatus::findBySystemKey('closed');

        StatusTransition::create([
            'entity_id' => $this->entity->id,
            'from_status_id' => $new->id,
            'to_status_id' => $closed->id,
            'label' => DemandButtonEnum::ENVIAR->value,
            'allowed_role_ids' => ['__requester__'],
            'display_order' => 1,
        ]);

        $this->demand = Demand::create([
            'title' => 'Demanda Smoke',
            'entity_id' => $this->entity->id,
            'process_status_id' => $new->id,
            'client_id' => $client->id,
            'requested_by' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_lista_de_demandas_redireciona_para_o_relatorio(): void
    {
        Livewire::test(ListDemands::class)
            ->assertRedirect(DemandReports::getUrl());
    }

    public function test_criacao_de_demanda_renderiza(): void
    {
        Livewire::test(CreateDemand::class)->assertSuccessful();
    }

    public function test_criacao_de_demanda_com_processo_selecionado_renderiza(): void
    {
        Livewire::test(CreateDemand::class)
            ->fillForm(['entity_id' => $this->entity->id])
            ->assertSuccessful();
    }

    public function test_edicao_de_demanda_renderiza_com_acoes_do_cabecalho(): void
    {
        Livewire::test(EditDemand::class, ['record' => $this->demand->getKey()])
            ->assertSuccessful();
    }

    public function test_visualizacao_de_demanda_renderiza(): void
    {
        Livewire::test(ViewDemand::class, ['record' => $this->demand->getKey()])
            ->assertSuccessful();
    }

    public function test_relatorio_de_demandas_renderiza(): void
    {
        Livewire::test(DemandReports::class)->assertSuccessful();
    }

    public function test_demanda_pode_ser_criada_pela_tela_de_criacao(): void
    {
        Livewire::test(CreateDemand::class)
            ->fillForm([
                'entity_id' => $this->entity->id,
                'client_id' => Client::first()->id,
                'project_id' => 'none',
                'title' => 'Demanda criada pela tela',
                'supplier_id' => 'none',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('demands', [
            'title' => 'Demanda criada pela tela',
            'status' => DemandStatusEnum::ACTIVE->value,
            'project_id' => null,
            'supplier_id' => null,
        ]);
    }

    public function test_projeto_e_obrigatorio_na_criacao_de_demanda(): void
    {
        Livewire::test(CreateDemand::class)
            ->fillForm([
                'entity_id' => $this->entity->id,
                'client_id' => Client::first()->id,
                'supplier_id' => 'none',
                'title' => 'Demanda sem projeto escolhido',
            ])
            ->call('create')
            ->assertHasFormErrors(['project_id' => 'required']);
    }

    public function test_fornecedor_e_obrigatorio_na_criacao_de_demanda(): void
    {
        Livewire::test(CreateDemand::class)
            ->fillForm([
                'entity_id' => $this->entity->id,
                'client_id' => Client::first()->id,
                'project_id' => 'none',
                'title' => 'Demanda sem fornecedor escolhido',
            ])
            ->call('create')
            ->assertHasFormErrors(['supplier_id' => 'required']);
    }

    public function test_demanda_pode_ser_criada_com_fornecedor_real(): void
    {
        $supplier = Supplier::create(['name' => 'Fornecedor ACME']);

        Livewire::test(CreateDemand::class)
            ->fillForm([
                'entity_id' => $this->entity->id,
                'client_id' => Client::first()->id,
                'project_id' => 'none',
                'supplier_id' => $supplier->id,
                'title' => 'Demanda com fornecedor real',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('demands', [
            'title' => 'Demanda com fornecedor real',
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_fornecedor_inexistente_falha_validacao_in(): void
    {
        Livewire::test(CreateDemand::class)
            ->fillForm([
                'entity_id' => $this->entity->id,
                'client_id' => Client::first()->id,
                'project_id' => 'none',
                'supplier_id' => 'fornecedor-invalido',
                'title' => 'Demanda com fornecedor invalido',
            ])
            ->call('create')
            ->assertHasFormErrors(['supplier_id' => 'in']);
    }

    public function test_edicao_de_demanda_sem_fornecedor_hidrata_none(): void
    {
        $this->assertNull($this->demand->supplier_id);

        Livewire::test(EditDemand::class, ['record' => $this->demand->getKey()])
            ->assertFormSet(['supplier_id' => 'none']);
    }

    public function test_demanda_pode_ser_salva_pela_tela_de_edicao(): void
    {
        Livewire::test(EditDemand::class, ['record' => $this->demand->getKey()])
            ->fillForm(['title' => 'Titulo editado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Titulo editado', $this->demand->fresh()->title);
    }

    public function test_tratamento_pode_ser_adicionado_na_tela_de_edicao(): void
    {
        Livewire::test(EditDemand::class, ['record' => $this->demand->getKey()])
            ->set('new_treatment', 'Tratamento de teste')
            ->call('addTreatment');

        $this->assertDatabaseHas('demand_comments', [
            'demand_id' => $this->demand->id,
            'comment' => 'Tratamento de teste',
        ]);
    }

    public function test_rascunho_enviado_pela_tela_de_edicao_calcula_o_sla(): void
    {
        $this->entity->update(['sla_hours' => 24]);

        $draft = Demand::create([
            'title' => 'Rascunho Smoke',
            'entity_id' => $this->entity->id,
            'process_status_id' => ProcessStatus::findBySystemKey('new')->id,
            'client_id' => Client::first()->id,
            'requested_by' => $this->admin->id,
            'created_by' => $this->admin->id,
            'status' => DemandStatusEnum::DRAFT,
        ]);
        $draft->updateQuietly(['sla_due_at' => null]);

        Livewire::test(EditDemand::class, ['record' => $draft->getKey()])
            ->call('submitDraft')
            ->assertHasNoFormErrors();

        $draft->refresh();
        $this->assertSame(DemandStatusEnum::ACTIVE, $draft->status);
        $this->assertNotNull($draft->sla_due_at);
    }

    public function test_demanda_pode_ser_cancelada_pelo_cabecalho_da_edicao(): void
    {
        Livewire::test(EditDemand::class, ['record' => $this->demand->getKey()])
            ->callAction('cancelar')
            ->assertHasNoActionErrors();

        $this->assertTrue($this->demand->fresh()->processStatus->isCanceled());
        $this->assertDatabaseHas('activity_logs', [
            'auditable_id' => (string) $this->demand->id,
            'event' => 'transition',
        ]);
    }

    public function test_demanda_com_subdemanda_pendente_nao_pode_ser_cancelada(): void
    {
        Demand::create([
            'title' => 'Subdemanda pendente',
            'entity_id' => $this->entity->id,
            'parent_demand_id' => $this->demand->id,
            'requested_by' => $this->admin->id,
            'created_by' => $this->admin->id,
            'status' => DemandStatusEnum::DRAFT,
        ]);

        $this->assertTrue($this->demand->hasPendingChildren());
        $this->assertFalse($this->demand->canBeCompleted());

        Livewire::test(EditDemand::class, ['record' => $this->demand->getKey()])
            ->callAction('cancelar');

        $this->assertFalse($this->demand->fresh()->processStatus->isCanceled());
    }

    public function test_edicao_de_demanda_exibe_botao_de_transicao_configurado(): void
    {
        $transition = StatusTransition::where('entity_id', $this->entity->id)->firstOrFail();

        Livewire::test(EditDemand::class, ['record' => $this->demand->getKey()])
            ->assertActionExists('transition_'.Str::slug($transition->id));
    }

    public function test_lista_de_projetos_renderiza(): void
    {
        $project = Project::create(['name' => 'Projeto Smoke']);

        Livewire::test(ListProjects::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$project]);
    }

    public function test_criacao_de_projeto_renderiza(): void
    {
        Livewire::test(CreateProject::class)->assertSuccessful();
    }

    public function test_edicao_de_projeto_renderiza(): void
    {
        $project = Project::create(['name' => 'Projeto Smoke']);

        Livewire::test(EditProject::class, ['record' => $project->getKey()])
            ->assertSuccessful();
    }

    public function test_tela_inicial_e_minhas_demandas_com_apenas_demandas_e_subdemandas(): void
    {
        $this->assertSame('Minhas Demandas', MyDemands::getNavigationLabel());
        $this->assertSame(url('/admin'), MyDemands::getUrl());
        $this->assertSame(MyDemands::getUrl(), Filament::getHomeUrl());
        $this->assertSame(MyDemands::getUrl(), Filament::getUrl());

        $component = Livewire::test(MyDemands::class)->assertSuccessful();

        $this->assertSame(
            [
                MyDemandsWidget::class,
                MySubDemandsWidget::class,
            ],
            array_values($component->instance()->getVisibleWidgets())
        );

        Livewire::test(MyDemandsWidget::class)
            ->assertSuccessful()
            ->assertSee('Minhas Demandas');

        Livewire::test(MySubDemandsWidget::class)
            ->assertSuccessful()
            ->assertSee('Minhas Subdemandas');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Minhas Demandas')
            ->assertSeeLivewire(MyDemandsWidget::class)
            ->assertSeeLivewire(MySubDemandsWidget::class)
            ->assertDontSeeLivewire(DemandStatsWidget::class)
            ->assertDontSeeLivewire(GeneralStatsOverview::class)
            ->assertDontSeeLivewire(DemandByProcessChart::class)
            ->assertDontSeeLivewire(DemandSLAChart::class);
    }

    public function test_pagina_painel_de_controle_exibe_metricas_e_graficos(): void
    {
        $this->assertSame('Painel de Controle', ControlPanel::getNavigationLabel());
        $this->assertSame(url('/admin/painel-de-controle'), ControlPanel::getUrl());

        $component = Livewire::test(ControlPanel::class)->assertSuccessful();

        $this->assertSame(
            [
                DemandStatsWidget::class,
                GeneralStatsOverview::class,
                DemandByProcessChart::class,
                DemandSLAChart::class,
            ],
            array_values($component->instance()->getVisibleWidgets())
        );

        Livewire::test(DemandStatsWidget::class)
            ->assertSuccessful()
            ->assertSee('Qtde de Demandas')
            ->assertSee('Projetos (membro)')
            ->assertSee('Demandas Atrasadas');

        Livewire::test(GeneralStatsOverview::class)
            ->assertSuccessful()
            ->assertSee('Demandas Pendentes')
            ->assertSee('Encerradas (Mês Atual)')
            ->assertSee('Total de Demandas');

        Livewire::test(DemandByProcessChart::class)
            ->assertSuccessful()
            ->assertSee('Demandas por Processo');

        Livewire::test(DemandSLAChart::class)
            ->assertSuccessful()
            ->assertSee('Cumprimento de Prazo (SLA)');

        $this->get('/admin/painel-de-controle')
            ->assertOk()
            ->assertSee('Painel de Controle')
            ->assertSeeLivewire(DemandStatsWidget::class)
            ->assertSeeLivewire(GeneralStatsOverview::class)
            ->assertSeeLivewire(DemandByProcessChart::class)
            ->assertSeeLivewire(DemandSLAChart::class)
            ->assertDontSeeLivewire(MyDemandsWidget::class)
            ->assertDontSeeLivewire(MySubDemandsWidget::class);
    }

    public function test_navegacao_exibe_minhas_demandas_antes_de_painel_de_controle(): void
    {
        $executor = User::factory()->create([
            'user_role' => UserRoleEnum::EXECUTOR,
            'is_active' => true,
        ]);

        foreach ([$this->admin, $executor] as $user) {
            $this->actingAs($user);

            $topGroup = collect(Filament::getNavigation())->first(fn ($group) => $group->getLabel() === null);
            $this->assertNotNull($topGroup);

            $items = collect($topGroup->getItems())->values();
            $this->assertCount(2, $items);

            $this->assertSame('Minhas Demandas', $items[0]->getLabel());
            $this->assertSame(MyDemands::getUrl(), $items[0]->getUrl());

            $this->assertSame('Painel de Controle', $items[1]->getLabel());
            $this->assertSame(ControlPanel::getUrl(), $items[1]->getUrl());
        }
    }
}
