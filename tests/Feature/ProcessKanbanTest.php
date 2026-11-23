<?php

namespace Tests\Feature;

use App\Enums\DemandButtonEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Pages\DemandReports;
use App\Filament\Resources\CustomEntityResource;
use App\Filament\Resources\DemandResource;
use App\Livewire\ProcessDemandsKanban;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\Macroprocess;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\TransitionRule;
use App\Models\User;
use App\Services\DemandTransitionService;
use App\Services\ProcessKanbanBoard;
use App\Services\ProcessVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class ProcessKanbanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CustomEntity $processo;

    private ProcessStatus $aprovando;

    private ProcessStatus $analise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $gestor = User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);

        $macro = Macroprocess::create([
            'name' => 'Macro Kanban', 'acronym' => 'KAN', 'value_chain_function' => 'Suportar Processos',
            'description' => 'Descrição', 'is_active' => true, 'created_by' => $this->admin->id, 'manager_id' => $gestor->id,
        ]);

        $this->processo = CustomEntity::create([
            'name' => 'Processo Kanban', 'macroprocess_id' => $macro->id, 'purpose' => 'Apoio',
            'description' => 'Descrição', 'is_active' => true, 'created_by' => $this->admin->id, 'manager_id' => $gestor->id,
        ]);

        // display_order "embaralhado" de propósito: o fluxo é que define a ordem das colunas
        $this->aprovando = ProcessStatus::factory()->create(['name' => 'Aprovando']);
        $this->analise = ProcessStatus::factory()->create(['name' => 'Em análise']);
        $this->processo->processStatuses()->attach($this->aprovando->id, ['display_order' => 1]);
        $this->processo->processStatuses()->attach($this->analise->id, ['display_order' => 2]);

        $new = ProcessStatus::findBySystemKey('new');
        $conditional = ProcessStatus::findBySystemKey('conditional');
        $closed = ProcessStatus::findBySystemKey('closed');

        // Nova -> (Condicional: regra -> Aprovando, senão Em análise); Em análise -> Aprovando -> Encerrada
        $entrada = StatusTransition::create([
            'entity_id' => $this->processo->id, 'from_status_id' => $new->id, 'to_status_id' => $conditional->id,
            'default_to_status_id' => $this->analise->id, 'label' => DemandButtonEnum::ENVIAR->value,
            'allowed_role_ids' => ['__requester__'], 'display_order' => 1,
        ]);
        TransitionRule::create([
            'status_transition_id' => $entrada->id, 'rule_order' => 0,
            'conditions' => [['field_id' => (string) Str::uuid(), 'operator' => 'gt', 'value' => '1']],
            'to_status_id' => $this->aprovando->id,
        ]);
        foreach ([[$this->analise, $this->aprovando], [$this->aprovando, $closed]] as $i => [$de, $para]) {
            StatusTransition::create([
                'entity_id' => $this->processo->id, 'from_status_id' => $de->id, 'to_status_id' => $para->id,
                'label' => DemandButtonEnum::ENVIAR->value, 'allowed_role_ids' => [], 'display_order' => $i + 2,
            ]);
        }
    }

    private function demanda(?ProcessStatus $status, array $extra = []): Demand
    {
        return Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'process_status_id' => $status?->id,
            'requested_by' => $this->admin->id,
            'created_by' => $this->admin->id,
        ] + $extra);
    }

    public function test_colunas_seguem_o_fluxo_sem_a_condicional_e_incluem_as_finais(): void
    {
        $nomes = app(ProcessKanbanBoard::class)->columns($this->processo)->pluck('name')->all();

        $this->assertSame('Nova', $nomes[0]);
        $this->assertSame(['Nova', 'Em análise', 'Aprovando', 'Encerrada'], array_slice($nomes, 0, 4));
        $this->assertNotContains('Condicional', $nomes);
        $this->assertContains('Avaliada', $nomes);
        $this->assertContains('Cancelada', $nomes);
        $this->assertNotContains('Fora do fluxo', $nomes, 'Sem demandas soltas, a coluna não aparece');
    }

    public function test_agrupa_demandas_por_situacao_e_isola_as_de_fora_do_fluxo(): void
    {
        $new = ProcessStatus::findBySystemKey('new');
        $this->demanda($new);
        $this->demanda($this->aprovando);
        $this->demanda($this->aprovando);
        $semSituacao = $this->demanda(null);
        $outraVersao = CustomEntity::factory()->create();
        Demand::factory()->create(['entity_id' => $outraVersao->id, 'process_status_id' => $this->aprovando->id]);

        $colunas = app(ProcessKanbanBoard::class)->columns($this->processo)->keyBy('name');

        $this->assertSame(1, $colunas['Nova']['total']);
        $this->assertSame(2, $colunas['Aprovando']['total']);
        $this->assertSame(0, $colunas['Em análise']['total']);
        $this->assertSame(1, $colunas['Fora do fluxo']['total']);
        $this->assertSame($semSituacao->id, $colunas['Fora do fluxo']['records']->first()->id);
    }

    public function test_limita_cards_por_coluna_mas_mantem_o_total(): void
    {
        Demand::factory()->count(ProcessKanbanBoard::CARDS_PER_COLUMN + 3)->create([
            'entity_id' => $this->processo->id, 'process_status_id' => $this->aprovando->id,
        ]);

        $coluna = app(ProcessKanbanBoard::class)->columns($this->processo)->firstWhere('name', 'Aprovando');

        $this->assertSame(ProcessKanbanBoard::CARDS_PER_COLUMN + 3, $coluna['total']);
        $this->assertCount(ProcessKanbanBoard::CARDS_PER_COLUMN, $coluna['records']);
    }

    public function test_numero_de_consultas_nao_cresce_com_as_demandas(): void
    {
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            app(ProcessKanbanBoard::class)->columns($this->processo);

            return count(DB::getQueryLog());
        };

        $this->demanda($this->aprovando, ['assigned_to' => User::factory()->create()->id]);
        $poucas = $contar();

        foreach (range(1, 15) as $_) {
            $this->demanda($this->aprovando, ['assigned_to' => User::factory()->create()->id]);
        }

        $this->assertSame($poucas, $contar());
    }

    public function test_secao_mostra_cards_com_link_para_a_demanda(): void
    {
        $this->actingAs($this->admin);
        $demanda = $this->demanda($this->aprovando, ['title' => 'Comprar notebooks']);

        Livewire::test(ProcessDemandsKanban::class, ['entityId' => $this->processo->id])
            ->assertSee('Quadro de demandas')
            ->assertSee('Aprovando')
            ->assertSee('Comprar notebooks')
            ->assertSee(DemandResource::getUrl('view', ['record' => $demanda]), false)
            ->assertDontSee('Condicional');
    }

    public function test_apenas_admin_acessa_a_secao(): void
    {
        $this->actingAs(User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true]));

        Livewire::test(ProcessDemandsKanban::class, ['entityId' => $this->processo->id])->assertForbidden();
    }

    public function test_secao_aparece_na_visualizacao_e_na_edicao_do_processo(): void
    {
        $this->actingAs($this->admin);

        foreach (['view', 'edit'] as $pagina) {
            $this->get(CustomEntityResource::getUrl($pagina, ['record' => $this->processo]))
                ->assertOk()
                ->assertSeeLivewire(ProcessDemandsKanban::class);
        }
    }

    public function test_encerrar_pelo_fluxo_mantem_encerrada_e_nao_trava_a_exclusao_da_versao(): void
    {
        $demanda = $this->demanda($this->aprovando);
        $transicao = StatusTransition::where('entity_id', $this->processo->id)
            ->where('from_status_id', $this->aprovando->id)->firstOrFail();

        app(DemandTransitionService::class)->execute($demanda, $transicao, $this->admin);

        $demanda->refresh();
        $this->assertTrue($demanda->processStatus->isClosed());
        $this->assertSame(DemandStatusEnum::CLOSED, $demanda->status);
        $this->assertNull($demanda->assigned_to);
        $this->assertFalse(app(ProcessVersioningService::class)->hasPendingDemands($this->processo));
    }

    public function test_fim_de_fluxo_em_situacao_que_nao_encerra_continua_concluida(): void
    {
        // Em análise sem saídas: chegou ao fim do fluxo sem encerrar a demanda
        StatusTransition::where('from_status_id', $this->analise->id)->delete();
        $demanda = $this->demanda($this->analise);

        $demanda->autoAssign();

        $this->assertSame(DemandStatusEnum::COMPLETED, $demanda->fresh()->status);
    }

    public function test_encerrada_com_prazo_passado_nao_recebe_a_classe_de_atraso(): void
    {
        $this->actingAs($this->admin);
        $closed = ProcessStatus::findBySystemKey('closed');
        $this->demanda($closed, [
            'status' => DemandStatusEnum::CLOSED, 'completed_at' => now()->subDay(), 'sla_due_at' => now()->subDays(5),
        ]);

        Livewire::test(ProcessDemandsKanban::class, ['entityId' => $this->processo->id])
            ->assertDontSee('text-danger-600', false);

        // Em andamento com o prazo vencido continua marcada como atrasada
        $this->demanda($this->aprovando, ['sla_due_at' => now()->subDays(2)]);

        Livewire::test(ProcessDemandsKanban::class, ['entityId' => $this->processo->id])
            ->assertSee('text-danger-600', false);
    }

    public function test_card_do_widget_do_painel_continua_marcando_atraso_em_situacao_final(): void
    {
        $demanda = $this->demanda(ProcessStatus::findBySystemKey('closed'), [
            'status' => DemandStatusEnum::CLOSED, 'sla_due_at' => now()->subDays(5),
        ]);
        $card = fn (bool $widget) => $this->blade(
            '<x-demand-kanban-card :demand="$demanda" href="#" :finished-can-be-overdue="$widget" />',
            ['demanda' => $demanda, 'widget' => $widget],
        );

        $card(true)->assertSee('text-danger-600', false);
        $card(false)->assertDontSee('text-danger-600', false);
    }

    public function test_coluna_encerrada_ordena_pela_conclusao_mais_recente(): void
    {
        $closed = ProcessStatus::findBySystemKey('closed');
        $extra = ['status' => DemandStatusEnum::CLOSED];
        // O prazo seguiria a ordem inversa: prova que as finais ignoram o SLA
        $antiga = $this->demanda($closed, $extra + ['completed_at' => now()->subDays(10), 'sla_due_at' => now()->subDays(20)]);
        $recente = $this->demanda($closed, $extra + ['completed_at' => now()->subDay(), 'sla_due_at' => now()->addDays(30)]);
        $semData = $this->demanda($closed, $extra + ['completed_at' => null, 'sla_due_at' => now()->subDays(30)]);
        $meio = $this->demanda($closed, $extra + ['completed_at' => now()->subDays(5), 'sla_due_at' => null]);

        $coluna = app(ProcessKanbanBoard::class)->columns($this->processo)->firstWhere('name', 'Encerrada');

        $this->assertSame(
            [$recente->id, $meio->id, $antiga->id, $semData->id],
            $coluna['records']->pluck('id')->all(),
        );
    }

    public function test_entity_id_do_componente_nao_pode_ser_alterado_pelo_cliente(): void
    {
        $this->actingAs($this->admin);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ProcessDemandsKanban::class, ['entityId' => $this->processo->id])
            ->set('entityId', CustomEntity::factory()->create()->id);
    }

    public function test_link_de_mais_cards_abre_o_relatorio_ja_filtrado_pelo_processo_e_pela_situacao(): void
    {
        $this->actingAs($this->admin);
        Demand::factory()->count(ProcessKanbanBoard::CARDS_PER_COLUMN + 1)->create([
            'entity_id' => $this->processo->id, 'process_status_id' => $this->aprovando->id,
        ]);
        $outraSituacao = $this->demanda($this->analise, ['title' => 'Demanda de outra situacao']);
        $outroProcesso = Demand::factory()->create([
            'entity_id' => CustomEntity::factory()->create()->id,
            'process_status_id' => $this->aprovando->id,
            'title' => 'Demanda de outro processo',
        ]);

        $url = DemandReports::getUrl(['tableFilters' => [
            'entity_id' => ['value' => $this->processo->id],
            'process_status_id' => ['values' => [$this->aprovando->id]],
        ]]);

        Livewire::test(ProcessDemandsKanban::class, ['entityId' => $this->processo->id])
            ->assertSee('+1 — ver na lista')
            ->assertSee(e($url), false);

        $this->get($url)->assertOk()->assertSeeLivewire(DemandReports::class);

        // O relatório aplica os filtros vindos da URL (falha sem o #[Url] em $tableFilters)
        Livewire::withQueryParams(['tableFilters' => [
            'entity_id' => ['value' => $this->processo->id],
            'process_status_id' => ['values' => [$this->aprovando->id]],
        ]])->test(DemandReports::class)
            ->assertSet('tableFilters.entity_id.value', $this->processo->id)
            ->assertCanNotSeeTableRecords(collect([$outraSituacao, $outroProcesso]));
    }

    public function test_coluna_fora_do_fluxo_linka_para_todas_as_demandas_do_processo(): void
    {
        $this->actingAs($this->admin);
        Demand::factory()->count(ProcessKanbanBoard::CARDS_PER_COLUMN + 1)->create([
            'entity_id' => $this->processo->id, 'process_status_id' => null,
        ]);

        $url = DemandReports::getUrl(['tableFilters' => ['entity_id' => ['value' => $this->processo->id]]]);

        Livewire::test(ProcessDemandsKanban::class, ['entityId' => $this->processo->id])
            ->assertSee('+1 — ver todas do processo')
            ->assertSee(e($url), false);
    }

    public function test_migration_corrige_encerradas_gravadas_como_concluidas_e_e_idempotente(): void
    {
        $closed = ProcessStatus::findBySystemKey('closed');
        $legada = $this->demanda($closed, ['status' => DemandStatusEnum::COMPLETED]);
        $jaEncerrada = $this->demanda($closed, ['status' => DemandStatusEnum::CLOSED]);
        // Concluída de verdade (fim de fluxo numa situação que não encerra) não é tocada
        $concluida = $this->demanda($this->analise, ['status' => DemandStatusEnum::COMPLETED]);
        $avaliada = $this->demanda($closed, ['status' => DemandStatusEnum::EVALUATED]);

        $migration = require database_path('migrations/2026_11_23_100000_fix_closed_demands_status_completed.php');
        $migration->up();
        $migration->up();

        $this->assertSame(DemandStatusEnum::CLOSED, $legada->fresh()->status);
        $this->assertSame(DemandStatusEnum::CLOSED, $jaEncerrada->fresh()->status);
        $this->assertSame(DemandStatusEnum::COMPLETED, $concluida->fresh()->status);
        $this->assertSame(DemandStatusEnum::EVALUATED, $avaliada->fresh()->status);
    }

    public function test_cards_seguem_o_prazo_crescente_com_sem_prazo_por_ultimo_e_criacao_recente_no_desempate(): void
    {
        $prazo = now()->addDays(3)->startOfSecond();
        $semPrazo = $this->demanda($this->aprovando, ['sla_due_at' => null, 'created_at' => now()->subDays(1)]);
        $tardio = $this->demanda($this->aprovando, ['sla_due_at' => now()->addDays(9), 'created_at' => now()->subDays(1)]);
        $empateAntiga = $this->demanda($this->aprovando, ['sla_due_at' => $prazo, 'created_at' => now()->subDays(5)]);
        $empateNova = $this->demanda($this->aprovando, ['sla_due_at' => $prazo, 'created_at' => now()->subDays(2)]);
        $vencida = $this->demanda($this->aprovando, ['sla_due_at' => now()->subDays(4), 'created_at' => now()->subDays(8)]);

        $coluna = app(ProcessKanbanBoard::class)->columns($this->processo)->firstWhere('name', 'Aprovando');

        $this->assertSame(
            [$vencida->id, $empateNova->id, $empateAntiga->id, $tardio->id, $semPrazo->id],
            $coluna['records']->pluck('id')->all(),
        );
    }

    public function test_fora_do_fluxo_inclui_demanda_em_situacao_que_nao_pertence_a_versao(): void
    {
        $alheia = ProcessStatus::factory()->create(['name' => 'Situacao de outra versao']);
        $demanda = $this->demanda($alheia, ['title' => 'Em situacao alheia']);
        $this->demanda($this->aprovando);

        $colunas = app(ProcessKanbanBoard::class)->columns($this->processo)->keyBy('name');

        $this->assertNotContains('Situacao de outra versao', $colunas->keys()->all());
        $this->assertSame(1, $colunas['Fora do fluxo']['total']);
        $this->assertSame([$demanda->id], $colunas['Fora do fluxo']['records']->pluck('id')->all());
        $this->assertSame(1, $colunas['Aprovando']['total']);
    }

    public function test_transicoes_de_qualquer_situacao_nao_alteram_a_ordem_das_colunas(): void
    {
        $antes = app(ProcessKanbanBoard::class)->orderedStatuses($this->processo)->pluck('name')->all();

        // De qualquer situação para Aprovando e para Em análise: não definem posição no fluxo
        foreach ([[$this->aprovando, 'Reabrir'], [$this->analise, 'Voltar']] as $i => [$destino, $rotulo]) {
            StatusTransition::create([
                'entity_id' => $this->processo->id, 'from_status_id' => null, 'to_status_id' => $destino->id,
                'label' => $rotulo, 'allowed_role_ids' => [], 'display_order' => 10 + $i,
            ]);
        }

        $depois = app(ProcessKanbanBoard::class)->orderedStatuses($this->processo)->pluck('name')->all();

        $this->assertSame(['Nova', 'Em análise', 'Aprovando', 'Encerrada'], array_slice($depois, 0, 4));
        $this->assertSame($antes, $depois);
    }
}
