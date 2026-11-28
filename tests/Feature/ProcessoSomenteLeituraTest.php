<?php

namespace Tests\Feature;

use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomEntityResource;
use App\Filament\Resources\CustomEntityResource\Pages\ViewCustomEntity;
use App\Filament\Resources\CustomEntityResource\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\CustomEntityResource\RelationManagers\MembersRelationManager;
use App\Filament\Resources\CustomEntityResource\RelationManagers\ProcessStatusesRelationManager;
use App\Filament\Resources\CustomEntityResource\RelationManagers\StatusTransitionsRelationManager;
use App\Filament\Resources\MacroprocessResource;
use App\Filament\Resources\MacroprocessResource\Pages\ViewMacroprocess;
use App\Livewire\ProcessDemandsKanban;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\Macroprocess;
use App\Models\ProcessMember;
use App\Models\ProcessRole;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\TransitionRule;
use App\Models\User;
use App\Services\ProcessVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Visualização somente leitura do processo por quem não é ADMIN
 * (Painel de Controle → Cadeia de Valor → macroprocesso → processo).
 */
class ProcessoSomenteLeituraTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $gestor;

    private Macroprocess $macro;

    private CustomEntity $processo;

    private CustomField $campo;

    private CustomField $campoLivre;

    private ProcessStatus $emAnalise;

    private ProcessStatus $emAprovacao;

    private ProcessStatus $situacaoLivre;

    private ProcessMember $membro;

    private StatusTransition $transicao;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->gestor = User::factory()->create(['name' => 'Gestora do Processo', 'user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);

        $this->macro = Macroprocess::create([
            'name' => 'Macro Consulta', 'acronym' => 'CON', 'value_chain_function' => 'Suportar Processos',
            'description' => 'Descrição', 'is_active' => true, 'created_by' => $this->admin->id, 'manager_id' => $this->gestor->id,
        ]);

        $this->processo = CustomEntity::create([
            'name' => 'Processo Consultado', 'macroprocess_id' => $this->macro->id, 'purpose' => 'Apoio', 'sla_hours' => 48,
            'description' => 'Descrição do processo consultado', 'is_active' => true,
            'created_by' => $this->admin->id, 'manager_id' => $this->gestor->id,
        ]);

        $this->campo = CustomField::factory()->create(['name' => 'Valor solicitado', 'field_type' => FieldTypeEnum::NUMBER]);
        $this->campoLivre = CustomField::factory()->create(['name' => 'Campo não vinculado']);
        $this->processo->fields()->attach($this->campo->id, ['field_order' => 1]);

        $this->emAnalise = ProcessStatus::factory()->create(['name' => 'Em análise']);
        $this->emAprovacao = ProcessStatus::factory()->create(['name' => 'Em aprovação']);
        $this->situacaoLivre = ProcessStatus::factory()->create(['name' => 'Situação não vinculada']);
        $this->processo->processStatuses()->attach($this->emAnalise->id, ['display_order' => 10]);
        $this->processo->processStatuses()->attach($this->emAprovacao->id, ['display_order' => 11]);

        $papel = ProcessRole::factory()->create(['name' => 'Aprovador']);
        $this->membro = ProcessMember::create(['entity_id' => $this->processo->id, 'user_id' => $this->gestor->id, 'process_role_id' => $papel->id]);

        $this->transicao = StatusTransition::create([
            'entity_id' => $this->processo->id,
            'from_status_id' => ProcessStatus::findBySystemKey('new')->id,
            'to_status_id' => ProcessStatus::findBySystemKey('conditional')->id,
            'default_to_status_id' => $this->emAnalise->id,
            'allowed_role_ids' => [$papel->id],
            'display_order' => 1,
        ]);
        TransitionRule::create([
            'status_transition_id' => $this->transicao->id,
            'rule_order' => 0,
            'conditions' => [['field_id' => $this->campo->id, 'operator' => 'gt', 'value' => '1000']],
            'to_status_id' => $this->emAprovacao->id,
        ]);
    }

    /** @return array<string, array{UserRoleEnum}> */
    public static function perfisSemAdmin(): array
    {
        return [
            'gestor' => [UserRoleEnum::GESTOR],
            'executor' => [UserRoleEnum::EXECUTOR],
            'visualizador' => [UserRoleEnum::VIEWER],
        ];
    }

    private function comoUsuario(UserRoleEnum $perfil = UserRoleEnum::EXECUTOR): User
    {
        $user = User::factory()->create(['user_role' => $perfil, 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    private function abrirRelationManager(string $manager)
    {
        return Livewire::test($manager, ['ownerRecord' => $this->processo->fresh(), 'pageClass' => ViewCustomEntity::class]);
    }

    /** Retrato de tudo que as ações dos relation managers poderiam alterar */
    private function estrutura(): array
    {
        return array_map(
            fn (string $tabela) => DB::table($tabela)->orderBy('id')->get()->toJson(),
            ['custom_entities', 'custom_entity_custom_field', 'entity_process_status', 'process_members', 'status_transitions', 'transition_rules'],
        );
    }

    #[DataProvider('perfisSemAdmin')]
    public function test_usuario_sem_admin_abre_o_processo_vigente_sem_kanban_nem_acoes(UserRoleEnum $perfil): void
    {
        $this->comoUsuario($perfil);

        $this->get(CustomEntityResource::getUrl('view', ['record' => $this->processo]))
            ->assertOk()
            ->assertSee('Processo Consultado')
            ->assertSee('Sistematização do processo:')
            // A trilha volta ao macroprocesso, nunca à listagem (que daria 403)
            ->assertSee('href="'.MacroprocessResource::getUrl('view', ['record' => $this->macro]).'"', false)
            ->assertDontSee('href="'.CustomEntityResource::getUrl('index').'"', false)
            ->assertDontSeeLivewire(ProcessDemandsKanban::class)
            ->assertDontSee('Quadro de demandas');

        $pagina = Livewire::test(ViewCustomEntity::class, ['record' => $this->processo->id])
            ->assertSuccessful()
            ->assertFormSet([
                'name' => 'Processo Consultado',
                'description' => 'Descrição do processo consultado',
                'sla_hours' => 48,
                'manager_id' => $this->gestor->id,
            ])
            ->assertActionHidden('edit')
            ->assertActionHidden('publish_version')
            ->assertActionHidden('edit_structure')
            ->assertActionHidden('go_to_draft')
            ->assertActionHidden('inactive_versions');

        foreach (['name', 'macroprocess_id', 'manager_id', 'is_active', 'purpose', 'sla_hours', 'description'] as $campo) {
            $pagina->assertFormFieldIsDisabled($campo);
        }

        $this->assertSame(
            [FieldsRelationManager::class, MembersRelationManager::class, ProcessStatusesRelationManager::class, StatusTransitionsRelationManager::class],
            array_values($pagina->instance()->getRelationManagers()),
        );

        // Sem item de menu: o resource continua restrito
        $this->assertFalse(CustomEntityResource::canAccess());
    }

    public function test_usuario_sem_admin_nao_ve_acoes_de_versionamento_com_demandas_e_rascunho(): void
    {
        Demand::factory()->create(['entity_id' => $this->processo->id]);
        $this->actingAs($this->admin);
        $versoes = app(ProcessVersioningService::class);
        $v2 = $versoes->publishVersion($versoes->createNewVersion($this->processo));
        Demand::factory()->create(['entity_id' => $v2->id]);

        $this->comoUsuario(UserRoleEnum::GESTOR);

        Livewire::test(ViewCustomEntity::class, ['record' => $v2->id])
            ->assertSuccessful()
            ->assertActionHidden('edit_structure')
            ->assertActionHidden('inactive_versions');

        // O aviso de estrutura travada fala de "Editar estrutura": não vale para quem só consulta
        foreach (CustomEntityResource::getRelations() as $manager) {
            Livewire::test($manager, ['ownerRecord' => $v2->fresh(), 'pageClass' => ViewCustomEntity::class])
                ->assertDontSee(ProcessVersioningService::LOCKED_STRUCTURE_MESSAGE);
        }

        $versoes->createNewVersion($v2);

        Livewire::test(ViewCustomEntity::class, ['record' => $v2->id])->assertActionHidden('go_to_draft');
    }

    #[DataProvider('perfisSemAdmin')]
    public function test_relation_managers_ficam_somente_leitura_para_usuario_sem_admin(UserRoleEnum $perfil): void
    {
        $this->comoUsuario($perfil);

        // Sem demandas a estrutura não está travada: só a visualização e o perfil protegem
        $this->abrirRelationManager(FieldsRelationManager::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($this->processo->fields)
            ->assertTableActionHidden('attach')
            ->assertTableActionHidden('detach', $this->campo)
            ->assertTableBulkActionHidden('detach');

        $this->abrirRelationManager(ProcessStatusesRelationManager::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->emAnalise, $this->emAprovacao])
            ->assertTableActionHidden('attach')
            ->assertTableActionHidden('detach', $this->emAnalise)
            ->assertTableBulkActionHidden('detach');

        $this->abrirRelationManager(MembersRelationManager::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->membro])
            ->assertSee('Gestora do Processo')
            ->assertSee('Aprovador')
            ->assertTableColumnHidden('user.user_role')
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('edit', $this->membro)
            ->assertTableActionHidden('delete', $this->membro)
            ->assertTableBulkActionHidden('delete');

        $this->abrirRelationManager(StatusTransitionsRelationManager::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->transicao])
            ->assertSee('Gestora do Processo')
            ->assertTableActionVisible('view', $this->transicao)
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('edit', $this->transicao)
            ->assertTableActionHidden('delete', $this->transicao);
    }

    /** Chamadas diretas ao Livewire (sem passar pelos botões) também são recusadas */
    public function test_servidor_recusa_escrita_de_usuario_sem_admin(): void
    {
        $this->comoUsuario();
        $outroUsuario = User::factory()->create();
        // Dois itens em cada lista reordenável, para uma reordenação indevida aparecer
        $outroCampo = CustomField::factory()->create();
        $this->processo->fields()->attach($outroCampo->id, ['field_order' => 2]);
        $outraTransicao = StatusTransition::create([
            'entity_id' => $this->processo->id, 'from_status_id' => $this->emAnalise->id, 'to_status_id' => $this->emAprovacao->id,
        ]);
        $antes = $this->estrutura();

        $forjar = function (string $manager, string $acao, ?string $registro = null, array $dados = []) {
            $componente = $this->abrirRelationManager($manager)->call('mountTableAction', $acao, $registro);

            foreach ($dados as $chave => $valor) {
                $componente->set("mountedTableActionsData.0.{$chave}", $valor);
            }

            $componente->call('callMountedTableAction');
        };

        $forjar(FieldsRelationManager::class, 'attach', null, ['recordId' => $this->campoLivre->id]);
        $forjar(FieldsRelationManager::class, 'detach', $this->campo->id);
        $forjar(ProcessStatusesRelationManager::class, 'attach', null, ['recordId' => $this->situacaoLivre->id]);
        $forjar(ProcessStatusesRelationManager::class, 'detach', $this->emAnalise->id);
        $forjar(MembersRelationManager::class, 'create', null, [
            'user_id' => $this->admin->id,
            'process_role_id' => $this->membro->process_role_id,
        ]);
        $forjar(MembersRelationManager::class, 'edit', $this->membro->id, ['user_id' => $outroUsuario->id]);
        $forjar(MembersRelationManager::class, 'delete', $this->membro->id);
        $forjar(StatusTransitionsRelationManager::class, 'create', null, [
            'from_status_id' => $this->emAprovacao->id,
            'to_status_id' => $this->emAnalise->id,
            'allowed_role_ids' => ['__requester__'],
        ]);
        $forjar(StatusTransitionsRelationManager::class, 'edit', $this->transicao->id, [
            'default_to_status_id' => $this->emAprovacao->id,
        ]);
        $forjar(StatusTransitionsRelationManager::class, 'delete', $this->transicao->id);

        foreach ([
            [FieldsRelationManager::class, 'detach', $this->campo->id],
            [ProcessStatusesRelationManager::class, 'detach', $this->emAnalise->id],
            [MembersRelationManager::class, 'delete', $this->membro->id],
        ] as [$manager, $acao, $registro]) {
            $this->abrirRelationManager($manager)
                ->set('selectedTableRecords', [$registro])
                ->call('mountTableBulkAction', $acao, [$registro])
                ->call('callMountedTableBulkAction');
        }

        // Reordenação: a ordem enviada é o inverso da gravada
        $this->abrirRelationManager(FieldsRelationManager::class)->call('reorderTable', [$outroCampo->id, $this->campo->id]);
        $this->abrirRelationManager(ProcessStatusesRelationManager::class)->call('reorderTable', [$this->emAprovacao->id, $this->emAnalise->id]);
        $this->abrirRelationManager(StatusTransitionsRelationManager::class)->call('reorderTable', [$outraTransicao->id, $this->transicao->id]);

        // O formulário da página também não grava nada
        Livewire::test(ViewCustomEntity::class, ['record' => $this->processo->id])
            ->set('data.name', 'Nome forjado')
            ->call('mountAction', 'edit')
            ->call('callMountedAction');

        $this->assertSame($antes, $this->estrutura());
    }

    public function test_usuario_sem_admin_ve_a_tabela_de_decisao_sem_conseguir_gravar(): void
    {
        $this->comoUsuario(UserRoleEnum::VIEWER);
        $antes = $this->estrutura();

        $componente = $this->abrirRelationManager(StatusTransitionsRelationManager::class)
            ->mountTableAction('view', $this->transicao)
            ->assertSee('Tabela de decisão');

        $this->assertNotEmpty($componente->get('mountedTableActionsData.0.rules'));

        // Mesmo adulterando o estado do modal de visualização, nada é persistido.
        $regras = array_map(
            fn (array $regra) => ['to_status_id' => $this->emAnalise->id] + $regra,
            $componente->get('mountedTableActionsData.0.rules'),
        );

        $componente
            ->set('mountedTableActionsData.0', array_merge($componente->get('mountedTableActionsData.0'), [
                'default_to_status_id' => $this->emAprovacao->id,
                'rules' => $regras,
            ]))
            ->call('callMountedTableAction')
            ->assertHasNoTableActionErrors();

        $this->assertSame($antes, $this->estrutura());
    }

    #[DataProvider('perfisSemAdmin')]
    public function test_usuario_sem_admin_nao_acessa_as_demais_paginas_do_processo(UserRoleEnum $perfil): void
    {
        $this->comoUsuario($perfil);

        foreach ([
            CustomEntityResource::getUrl('index'),
            CustomEntityResource::getUrl('create'),
            CustomEntityResource::getUrl('edit', ['record' => $this->processo]),
            CustomEntityResource::getUrl('permissions', ['record' => $this->processo]),
            CustomEntityResource::getUrl('inactive-versions'),
            CustomEntityResource::getUrl('inactive-versions', ['processo' => $this->processo->id]),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_usuario_sem_admin_nao_acessa_rascunho_nem_versao_antiga(): void
    {
        $this->actingAs($this->admin);
        $versoes = app(ProcessVersioningService::class);
        $v2 = $versoes->publishVersion($versoes->createNewVersion($this->processo));
        $rascunho = $versoes->createNewVersion($v2);

        $this->comoUsuario();

        foreach ([$this->processo, $rascunho] as $versao) {
            $this->get(CustomEntityResource::getUrl('view', ['record' => $versao]))->assertForbidden();
            // Nem a edição da versão antiga (que redireciona o ADMIN) escapa do bloqueio
            $this->get(CustomEntityResource::getUrl('edit', ['record' => $versao]))->assertForbidden();
        }

        $this->get(CustomEntityResource::getUrl('view', ['record' => $v2]))->assertOk();

        $this->assertFalse(FieldsRelationManager::canViewForRecord($rascunho, ViewCustomEntity::class));
        $this->assertFalse(FieldsRelationManager::canViewForRecord($this->processo->fresh(), ViewCustomEntity::class));
    }

    public function test_macroprocesso_leva_o_usuario_sem_admin_a_visualizacao_do_processo(): void
    {
        $this->actingAs($this->admin);
        $versoes = app(ProcessVersioningService::class);
        $v2 = $versoes->publishVersion($versoes->createNewVersion($this->processo));
        $rascunho = $versoes->createNewVersion($v2);

        $this->comoUsuario();

        Livewire::test(ViewMacroprocess::class, ['record' => $this->macro->id])
            ->assertSuccessful()
            ->assertSeeHtml('href="'.CustomEntityResource::getUrl('view', ['record' => $v2]).'"')
            ->assertDontSeeHtml(CustomEntityResource::getUrl('edit', ['record' => $v2]))
            ->assertDontSeeHtml(CustomEntityResource::getUrl('view', ['record' => $rascunho]))
            ->assertDontSeeHtml(CustomEntityResource::getUrl('view', ['record' => $this->processo]))
            ->assertDontSee('Versões antigas de processos');
    }

    public function test_admin_continua_igual(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ViewMacroprocess::class, ['record' => $this->macro->id])
            ->assertSeeHtml('href="'.CustomEntityResource::getUrl('edit', ['record' => $this->processo]).'"');

        $this->get(CustomEntityResource::getUrl('view', ['record' => $this->processo]))
            ->assertOk()
            ->assertSee('href="'.CustomEntityResource::getUrl('index').'"', false)
            ->assertSeeLivewire(ProcessDemandsKanban::class);

        Livewire::test(ViewCustomEntity::class, ['record' => $this->processo->id])
            ->assertActionVisible('edit')
            ->assertFormFieldIsEnabled('name');

        $this->abrirRelationManager(MembersRelationManager::class)->assertTableColumnVisible('user.user_role');

        $versoes = app(ProcessVersioningService::class);
        $v2 = $versoes->publishVersion($versoes->createNewVersion($this->processo));
        $rascunho = $versoes->createNewVersion($v2);

        Livewire::test(ViewMacroprocess::class, ['record' => $this->macro->id])
            ->assertSee('Versões antigas de processos')
            ->assertSeeHtml(CustomEntityResource::getUrl('view', ['record' => $this->processo]));

        foreach ([$this->processo, $rascunho] as $versao) {
            $this->get(CustomEntityResource::getUrl('view', ['record' => $versao]))->assertOk();
        }
    }
}
