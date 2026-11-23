<?php

namespace Tests\Feature;

use App\Enums\DemandButtonEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\ProcessDeletionBlockedException;
use App\Exceptions\ProcessVersioningException;
use App\Filament\Pages\DemandReports;
use App\Filament\Resources\CustomEntityResource;
use App\Filament\Resources\CustomEntityResource\Pages\EditCustomEntity;
use App\Filament\Resources\CustomEntityResource\Pages\ListCustomEntities;
use App\Filament\Resources\CustomEntityResource\Pages\ListInactiveCustomEntityVersions;
use App\Filament\Resources\CustomEntityResource\Pages\ManageFieldPermissions;
use App\Filament\Resources\CustomEntityResource\Pages\ViewCustomEntity;
use App\Filament\Resources\CustomEntityResource\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\CustomEntityResource\RelationManagers\MembersRelationManager;
use App\Filament\Resources\CustomEntityResource\RelationManagers\ProcessStatusesRelationManager;
use App\Filament\Resources\CustomEntityResource\RelationManagers\StatusTransitionsRelationManager;
use App\Filament\Resources\DemandResource\Pages\CreateDemand;
use App\Filament\Resources\MacroprocessResource\Pages\ViewMacroprocess;
use App\Filament\Resources\ProcessRoleResource\Pages\ViewProcessRole;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\FieldPermission;
use App\Models\Macroprocess;
use App\Models\ProcessMember;
use App\Models\ProcessRole;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\TransitionRule;
use App\Models\User;
use App\Policies\CustomEntityPolicy;
use App\Services\DemandTransitionService;
use App\Services\ProcessVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class VersionamentoProcessosTest extends TestCase
{
    use RefreshDatabase;

    private ProcessVersioningService $service;

    private User $admin;

    private User $gestor;

    private Macroprocess $macro;

    private CustomEntity $v1;

    private CustomField $campoA;

    private CustomField $campoB;

    private ProcessStatus $emAnalise;

    private ProcessStatus $emAprovacao;

    private ProcessStatus $emValidacao;

    private ProcessRole $papel;

    private StatusTransition $transicaoCondicional;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->service = app(ProcessVersioningService::class);

        $this->admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->gestor = User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);

        $this->macro = Macroprocess::create([
            'name' => 'Macro Versionamento',
            'acronym' => 'VER',
            'value_chain_function' => 'Suportar Processos',
            'description' => 'Descrição',
            'is_active' => true,
            'created_by' => $this->admin->id,
            'manager_id' => $this->gestor->id,
        ]);

        $this->v1 = CustomEntity::create([
            'name' => 'Processo Versionado',
            'macroprocess_id' => $this->macro->id,
            'purpose' => 'Apoio',
            'sla_hours' => 72,
            'description' => 'Descrição do processo',
            'is_active' => true,
            'created_by' => $this->admin->id,
            'manager_id' => $this->gestor->id,
        ]);

        $this->campoA = CustomField::factory()->create(['name' => 'Campo A', 'field_type' => FieldTypeEnum::NUMBER]);
        $this->campoB = CustomField::factory()->create(['name' => 'Campo B', 'field_type' => FieldTypeEnum::TEXT]);
        $this->v1->fields()->attach($this->campoB->id, ['field_order' => 2]);
        $this->v1->fields()->attach($this->campoA->id, ['field_order' => 1]);

        $this->emAnalise = ProcessStatus::factory()->create(['name' => 'Em análise']);
        $this->emAprovacao = ProcessStatus::factory()->create(['name' => 'Em aprovação']);
        $this->emValidacao = ProcessStatus::factory()->create(['name' => 'Em validação']);
        $this->v1->processStatuses()->attach($this->emAnalise->id, ['display_order' => 10]);
        $this->v1->processStatuses()->attach($this->emAprovacao->id, ['display_order' => 11]);
        $this->v1->processStatuses()->attach($this->emValidacao->id, ['display_order' => 12]);

        $new = ProcessStatus::findBySystemKey('new');
        $conditional = ProcessStatus::findBySystemKey('conditional');

        $this->transicaoCondicional = StatusTransition::create([
            'entity_id' => $this->v1->id,
            'from_status_id' => $new->id,
            'to_status_id' => $conditional->id,
            'default_to_status_id' => $this->emValidacao->id,
            'label' => DemandButtonEnum::ENVIAR->value,
            'allowed_role_ids' => ['__requester__'],
            'display_order' => 1,
        ]);
        TransitionRule::create([
            'status_transition_id' => $this->transicaoCondicional->id,
            'rule_order' => 0,
            'conditions' => [['field_id' => $this->campoA->id, 'operator' => 'gt', 'value' => '1000']],
            'to_status_id' => $this->emAprovacao->id,
        ]);
        StatusTransition::create([
            'entity_id' => $this->v1->id,
            'from_status_id' => $this->emAprovacao->id,
            'to_status_id' => $this->emAnalise->id,
            'label' => DemandButtonEnum::ENVIAR->value,
            'display_order' => 2,
        ]);

        $this->papel = ProcessRole::factory()->create(['name' => 'Aprovador']);
        ProcessMember::create(['entity_id' => $this->v1->id, 'user_id' => $this->gestor->id, 'process_role_id' => $this->papel->id]);

        FieldPermission::create([
            'entity_id' => $this->v1->id,
            'custom_field_id' => $this->campoA->id,
            'process_status_id' => $new->id,
            'process_role_id' => null,
            'permission' => FieldPermissionEnum::REQUIRED,
        ]);
        FieldPermission::create([
            'entity_id' => $this->v1->id,
            'custom_field_id' => $this->campoB->id,
            'process_status_id' => $new->id,
            'process_role_id' => $this->papel->id,
            'permission' => FieldPermissionEnum::HIDDEN,
        ]);
    }

    private function demanda(CustomEntity $entity, DemandStatusEnum $status = DemandStatusEnum::ACTIVE, array $attributes = []): Demand
    {
        return Demand::factory()->create(array_merge([
            'entity_id' => $entity->id,
            'process_status_id' => ProcessStatus::findBySystemKey('new')->id,
            'status' => $status,
        ], $attributes));
    }

    // ------------------------------------------------------------------
    // Atributos de versão e serviço
    // ------------------------------------------------------------------

    public function test_processo_novo_nasce_como_v1_vigente_da_propria_raiz(): void
    {
        $this->assertSame($this->v1->id, $this->v1->fresh()->root_entity_id);
        $this->assertSame(1, $this->v1->fresh()->version);
        $this->assertTrue($this->v1->fresh()->is_current);
        $this->assertNull($this->v1->fresh()->previous_version_id);

        $viaFactory = CustomEntity::factory()->create();
        $this->assertSame($viaFactory->id, $viaFactory->fresh()->root_entity_id);
        $this->assertTrue($viaFactory->fresh()->is_current);
    }

    public function test_versao_vigencia_e_raiz_nao_sao_preenchiveis_em_massa(): void
    {
        $entity = CustomEntity::create([
            'name' => 'Tentativa',
            'description' => 'x',
            'created_by' => $this->admin->id,
            'version' => 9,
            'is_current' => false,
            'root_entity_id' => $this->v1->id,
            'previous_version_id' => $this->v1->id,
        ]);

        $entity = $entity->fresh();
        $this->assertSame(1, $entity->version);
        $this->assertTrue($entity->is_current);
        $this->assertSame($entity->id, $entity->root_entity_id);
        $this->assertNull($entity->previous_version_id);
    }

    public function test_nova_versao_copia_o_registro_e_marca_vigencia(): void
    {
        $this->actingAs($this->admin);

        $v2 = $this->service->createNewVersion($this->v1);

        $this->assertNotSame($this->v1->id, $v2->id);
        $this->assertSame(2, $v2->version);
        $this->assertTrue($v2->is_current);
        $this->assertSame($this->v1->id, $v2->root_entity_id);
        $this->assertSame($this->v1->id, $v2->previous_version_id);
        $this->assertFalse($this->v1->fresh()->is_current);
        $this->assertTrue($this->v1->fresh()->is_active, 'A versão antiga permanece ativa');

        $this->assertSame('Processo Versionado', $v2->name);
        $this->assertSame($this->macro->id, $v2->macroprocess_id);
        $this->assertSame('Apoio', $v2->purpose);
        $this->assertSame(72, $v2->sla_hours);
        $this->assertSame($this->gestor->id, $v2->manager_id);
        $this->assertSame('Descrição do processo', $v2->description);
        $this->assertSame($this->admin->id, $v2->created_by);

        // Código interno próprio (índice único); a interface exibe o da raiz + versão
        $this->assertNotSame($this->v1->code, $v2->code);
        $this->assertSame("{$this->v1->code} · v2", $v2->display_code);
        $this->assertSame("{$this->v1->code} · v1", $this->v1->fresh()->display_code);
        $this->assertSame('v2', $v2->version_label);
        $this->assertSame('VER - Processo Versionado (v2)', $v2->full_display_name);
        $this->assertSame('VER - Processo Versionado', $this->v1->fresh()->full_display_name);
    }

    public function test_nova_versao_copia_campos_situacoes_transicoes_regras_permissoes_e_membros(): void
    {
        $v2 = $this->service->createNewVersion($this->v1);

        // Campos, na mesma ordem
        $this->assertSame(
            [$this->campoA->id, $this->campoB->id],
            $v2->fields()->get()->pluck('id')->all()
        );
        $this->assertSame(
            $this->v1->fields()->get()->map(fn ($f) => $f->pivot->field_order)->all(),
            $v2->fields()->get()->map(fn ($f) => $f->pivot->field_order)->all()
        );

        // Situações: as de sistema não são duplicadas e a ordem é preservada
        $statusesV1 = $this->v1->processStatuses()->get();
        $statusesV2 = $v2->processStatuses()->get();
        $this->assertCount($statusesV1->count(), $statusesV2);
        $this->assertSame($statusesV1->pluck('id')->all(), $statusesV2->pluck('id')->all());
        $this->assertSame(
            $statusesV1->map(fn ($s) => $s->pivot->display_order)->all(),
            $statusesV2->map(fn ($s) => $s->pivot->display_order)->all()
        );
        $this->assertSame(1, $statusesV2->where('system_key', 'new')->count());

        // Transições: novos ids, mesmos dados
        $transicoesV1 = StatusTransition::where('entity_id', $this->v1->id)->orderBy('display_order')->get();
        $transicoesV2 = StatusTransition::where('entity_id', $v2->id)->orderBy('display_order')->get();
        $this->assertCount(2, $transicoesV2);
        $this->assertEmpty($transicoesV1->pluck('id')->intersect($transicoesV2->pluck('id')));
        foreach ($transicoesV1 as $i => $original) {
            $copia = $transicoesV2[$i];
            $this->assertSame($original->from_status_id, $copia->from_status_id);
            $this->assertSame($original->to_status_id, $copia->to_status_id);
            $this->assertSame($original->default_to_status_id, $copia->default_to_status_id);
            $this->assertSame($original->label, $copia->label);
            $this->assertSame($original->allowed_role_ids, $copia->allowed_role_ids);
            $this->assertSame($original->display_order, $copia->display_order);
        }

        // Regras: apontam para a transição copiada, não para a original
        $copiaCondicional = $transicoesV2->first(fn ($t) => $t->default_to_status_id === $this->emValidacao->id);
        $regras = TransitionRule::where('status_transition_id', $copiaCondicional->id)->get();
        $this->assertCount(1, $regras);
        $this->assertSame($this->emAprovacao->id, $regras->first()->to_status_id);
        $this->assertSame(
            [['field_id' => $this->campoA->id, 'operator' => 'gt', 'value' => '1000']],
            $regras->first()->conditions
        );
        $this->assertCount(1, TransitionRule::where('status_transition_id', $this->transicaoCondicional->id)->get(), 'A regra original continua na v1');
        $this->assertSame(2, TransitionRule::count());

        // Permissões de campo, inclusive a genérica (sem papel)
        $permissoes = FieldPermission::where('entity_id', $v2->id)->get();
        $this->assertCount(2, $permissoes);
        $this->assertSame(FieldPermissionEnum::REQUIRED, $permissoes->firstWhere('process_role_id', null)->permission);
        $this->assertSame(FieldPermissionEnum::HIDDEN, $permissoes->firstWhere('process_role_id', $this->papel->id)->permission);

        // Membros
        $membros = ProcessMember::where('entity_id', $v2->id)->get();
        $this->assertCount(1, $membros);
        $this->assertSame($this->gestor->id, $membros->first()->user_id);
        $this->assertSame($this->papel->id, $membros->first()->process_role_id);

        // A v1 ficou intacta
        $this->assertCount(2, FieldPermission::where('entity_id', $this->v1->id)->get());
        $this->assertCount(1, ProcessMember::where('entity_id', $this->v1->id)->get());
    }

    public function test_nova_versao_so_a_partir_da_vigente_e_incrementa_a_versao(): void
    {
        $v2 = $this->service->createNewVersion($this->v1);

        try {
            $this->service->createNewVersion($this->v1->fresh());
            $this->fail('Era esperada exceção ao versionar uma versão antiga');
        } catch (ProcessVersioningException $e) {
            $this->assertStringContainsString('versão vigente', $e->getMessage());
        }

        $v3 = $this->service->createNewVersion($v2);

        $this->assertSame(3, $v3->version);
        $this->assertSame($this->v1->id, $v3->root_entity_id);
        $this->assertSame($v2->id, $v3->previous_version_id);
        $this->assertSame("{$this->v1->code} · v3", $v3->display_code);
        $this->assertSame([1, 2, 3], $this->v1->versions()->pluck('version')->all());
        $this->assertSame(1, CustomEntity::where('root_entity_id', $this->v1->id)->current()->count());
        $this->assertSame(2, CustomEntity::where('root_entity_id', $this->v1->id)->inactiveVersions()->count());
        $this->assertSame($this->v1->id, $v3->rootEntity->id);
        $this->assertSame($v2->id, $v3->previousVersion->id);
    }

    public function test_demanda_antiga_continua_tramitando_na_v1_depois_da_v2(): void
    {
        $demanda = $this->demanda($this->v1);
        $this->service->createNewVersion($this->v1);

        $executor = User::factory()->create(['user_role' => UserRoleEnum::EXECUTOR]);
        $demanda->update(['requested_by' => $executor->id]);

        // Valor 1500 > 1000: a regra da v1 leva a "Em aprovação"
        DemandFieldValue::create(['demand_id' => $demanda->id, 'custom_field_id' => $this->campoA->id, 'value' => '1500']);

        $alvo = app(DemandTransitionService::class)->execute($demanda->fresh(), $this->transicaoCondicional, $executor);

        $this->assertSame($this->emAprovacao->id, $alvo->id);
        $this->assertSame($this->emAprovacao->id, $demanda->fresh()->process_status_id);
        $this->assertSame($this->v1->id, $demanda->fresh()->entity_id, 'A demanda continua na v1');
    }

    // ------------------------------------------------------------------
    // Estrutura travada
    // ------------------------------------------------------------------

    public function test_estrutura_travada_quando_ha_demandas_e_liberada_quando_nao_ha(): void
    {
        $this->assertFalse($this->service->hasDemands($this->v1));
        $this->assertFalse($this->service->isStructureLocked($this->v1));
        $this->assertNull($this->service->lockedMessage($this->v1));

        $this->demanda($this->v1, DemandStatusEnum::DRAFT);

        $this->assertTrue($this->service->hasDemands($this->v1));
        $this->assertTrue($this->service->isStructureLocked($this->v1));
        $this->assertSame(ProcessVersioningService::LOCKED_STRUCTURE_MESSAGE, $this->service->lockedMessage($this->v1));

        $v2 = $this->service->createNewVersion($this->v1);
        $this->assertFalse($this->service->isStructureLocked($v2), 'A v2 nasce sem demandas, estrutura liberada');
        $this->assertTrue($this->service->isStructureLocked($this->v1->fresh()), 'A v1 antiga fica travada');
    }

    public function test_relation_managers_ficam_somente_leitura_com_demandas(): void
    {
        $this->actingAs($this->admin);
        $page = EditCustomEntity::class;

        $abrir = fn (string $manager, CustomEntity $entity) => Livewire::test($manager, ['ownerRecord' => $entity, 'pageClass' => $page]);

        // Sem demandas: tudo liberado
        $abrir(FieldsRelationManager::class, $this->v1)->assertTableActionVisible('attach')->assertTableActionVisible('detach', $this->campoA);
        $abrir(ProcessStatusesRelationManager::class, $this->v1)->assertTableActionVisible('attach');
        $abrir(MembersRelationManager::class, $this->v1)->assertTableActionVisible('create');
        $abrir(StatusTransitionsRelationManager::class, $this->v1)
            ->assertTableActionVisible('create')
            ->assertTableActionVisible('edit', $this->transicaoCondicional)
            ->assertTableActionVisible('delete', $this->transicaoCondicional);

        $this->demanda($this->v1);

        // Com demandas: sem vincular, desvincular, criar, editar nem excluir; aviso na tabela
        $abrir(FieldsRelationManager::class, $this->v1->fresh())
            ->assertTableActionHidden('attach')
            ->assertTableActionHidden('detach', $this->campoA)
            ->assertSee(ProcessVersioningService::LOCKED_STRUCTURE_MESSAGE);
        $abrir(ProcessStatusesRelationManager::class, $this->v1->fresh())
            ->assertTableActionHidden('attach')
            ->assertSee(ProcessVersioningService::LOCKED_STRUCTURE_MESSAGE);
        $membro = ProcessMember::where('entity_id', $this->v1->id)->first();
        $abrir(MembersRelationManager::class, $this->v1->fresh())
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('edit', $membro)
            ->assertTableActionHidden('delete', $membro);
        $abrir(StatusTransitionsRelationManager::class, $this->v1->fresh())
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('edit', $this->transicaoCondicional)
            ->assertTableActionHidden('delete', $this->transicaoCondicional);
    }

    public function test_relation_managers_da_versao_antiga_ficam_somente_leitura(): void
    {
        $this->actingAs($this->admin);
        $v2 = $this->service->createNewVersion($this->v1);
        $page = EditCustomEntity::class;

        // v1 antiga (sem demandas, mas não vigente): travada
        Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $this->v1->fresh(), 'pageClass' => $page])
            ->assertTableActionHidden('attach')
            ->assertSee(ProcessVersioningService::INACTIVE_VERSION_MESSAGE);

        // v2 vigente e sem demandas: liberada
        Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $v2, 'pageClass' => $page])
            ->assertTableActionVisible('attach');
    }

    public function test_permissoes_de_campo_travadas_com_demandas(): void
    {
        $this->actingAs($this->admin);
        $new = ProcessStatus::findBySystemKey('new');

        // Sem demandas: salva normalmente
        Livewire::test(ManageFieldPermissions::class, ['record' => $this->v1->id])
            ->assertSet('matrix.'.$this->campoA->id.'.'.$new->id, FieldPermissionEnum::REQUIRED->value)
            ->set('matrix.'.$this->campoA->id.'.'.$new->id, FieldPermissionEnum::OPTIONAL->value)
            ->call('save');
        $this->assertSame(
            FieldPermissionEnum::OPTIONAL,
            FieldPermission::where('entity_id', $this->v1->id)->where('custom_field_id', $this->campoA->id)->whereNull('process_role_id')->first()->permission
        );

        // Com demandas: a grade não grava e a tela avisa
        $this->demanda($this->v1);

        Livewire::test(ManageFieldPermissions::class, ['record' => $this->v1->id])
            ->assertSee(ProcessVersioningService::LOCKED_STRUCTURE_MESSAGE)
            ->assertDontSee('Salvar Permissões')
            ->set('matrix.'.$this->campoA->id.'.'.$new->id, FieldPermissionEnum::HIDDEN->value)
            ->call('save');
        $this->assertSame(
            FieldPermissionEnum::OPTIONAL,
            FieldPermission::where('entity_id', $this->v1->id)->where('custom_field_id', $this->campoA->id)->whereNull('process_role_id')->first()->permission
        );
    }

    // ------------------------------------------------------------------
    // "Editar estrutura" e metadados
    // ------------------------------------------------------------------

    public function test_editar_estrutura_so_aparece_com_demandas_e_cria_a_nova_versao(): void
    {
        $this->actingAs($this->admin);

        // Sem demandas a estrutura é editada direto: não há o que versionar
        Livewire::test(EditCustomEntity::class, ['record' => $this->v1->id])
            ->assertActionHidden('edit_structure');
        Livewire::test(ViewCustomEntity::class, ['record' => $this->v1->id])
            ->assertActionHidden('edit_structure');

        $this->demanda($this->v1);

        Livewire::test(ViewCustomEntity::class, ['record' => $this->v1->id])
            ->assertActionVisible('edit_structure');

        $component = Livewire::test(EditCustomEntity::class, ['record' => $this->v1->id])
            ->assertActionVisible('edit_structure')
            ->callAction('edit_structure');

        $v2 = CustomEntity::where('root_entity_id', $this->v1->id)->where('version', 2)->first();
        $this->assertNotNull($v2);
        $this->assertTrue($v2->is_current);
        $this->assertFalse($this->v1->fresh()->is_current);
        $component->assertRedirect(CustomEntityResource::getUrl('edit', ['record' => $v2]));

        // Na v2 (sem demandas) a ação some: estrutura já liberada
        Livewire::test(EditCustomEntity::class, ['record' => $v2->id])
            ->assertActionHidden('edit_structure');
    }

    public function test_editar_estrutura_exige_admin(): void
    {
        $this->demanda($this->v1);

        $this->actingAs($this->gestor);
        $this->assertFalse(CustomEntityResource::canAccess());

        $this->actingAs($this->admin);
        $this->assertTrue(CustomEntityResource::canAccess());
    }

    public function test_editar_so_metadados_nao_cria_versao(): void
    {
        $this->actingAs($this->admin);
        $this->demanda($this->v1);
        $outroGestor = User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);

        Livewire::test(EditCustomEntity::class, ['record' => $this->v1->id])
            ->fillForm([
                'name' => 'Processo Renomeado',
                'manager_id' => $outroGestor->id,
                'sla_hours' => 10,
                'description' => 'Nova descrição',
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $v1 = $this->v1->fresh();
        $this->assertSame('Processo Renomeado', $v1->name);
        $this->assertSame($outroGestor->id, $v1->manager_id);
        $this->assertSame(10, $v1->sla_hours);
        $this->assertFalse($v1->is_active);
        $this->assertTrue($v1->is_current);
        $this->assertSame(1, $v1->version);
        $this->assertSame(1, CustomEntity::where('root_entity_id', $this->v1->id)->count());
    }

    public function test_versao_nao_e_editavel_pelo_formulario(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(EditCustomEntity::class, ['record' => $this->v1->id])
            ->assertFormFieldDoesNotExist('version')
            ->assertSee('Versão')
            ->assertSee('v1')
            ->fillForm(['name' => 'Outro nome'])
            ->set('data.version', 7)
            ->set('data.is_current', false)
            ->call('save');

        $v1 = $this->v1->fresh();
        $this->assertSame(1, $v1->version);
        $this->assertTrue($v1->is_current);
        $this->assertSame('Outro nome', $v1->name);
    }

    public function test_versao_antiga_e_somente_leitura(): void
    {
        $this->actingAs($this->admin);
        $this->service->createNewVersion($this->v1);
        $antiga = $this->v1->fresh();

        $this->assertFalse((new CustomEntityPolicy)->update($this->admin, $antiga));
        $this->assertTrue((new CustomEntityPolicy)->update($this->admin, $this->v1->versions()->where('version', 2)->first()));

        // A edição leva para a visualização
        Livewire::test(EditCustomEntity::class, ['record' => $antiga->id])
            ->assertRedirect(CustomEntityResource::getUrl('view', ['record' => $antiga]));

        // A visualização abre, sem ação de editar nem de nova versão
        Livewire::test(ViewCustomEntity::class, ['record' => $antiga->id])
            ->assertSuccessful()
            ->assertActionHidden('edit')
            ->assertActionHidden('edit_structure');
        $this->assertSame($antiga->name, $antiga->fresh()->name);
    }

    // ------------------------------------------------------------------
    // Nova demanda só na versão vigente
    // ------------------------------------------------------------------

    public function test_select_de_nova_demanda_lista_so_a_versao_vigente_com_a_versao_no_rotulo(): void
    {
        $this->actingAs($this->admin);
        $v2 = $this->service->createNewVersion($this->v1);

        $component = Livewire::test(CreateDemand::class);
        $select = $component->instance()->form->getFlatFields()['entity_id'];
        $options = $select->getOptions();

        $this->assertArrayHasKey($v2->id, $options);
        $this->assertArrayNotHasKey($this->v1->id, $options);
        $this->assertSame('VER - Processo Versionado (v2)', $options[$v2->id]);

        // Versão inativa por is_active também continua fora
        $v2->update(['is_active' => false]);
        $this->assertArrayNotHasKey($v2->id, $component->instance()->form->getFlatFields()['entity_id']->getOptions());
    }

    public function test_nao_cria_demanda_em_versao_antiga(): void
    {
        $this->actingAs($this->admin);
        $v2 = $this->service->createNewVersion($this->v1);
        $client = Client::create(['name' => 'Cliente Versão', 'is_active' => true]);

        $dados = fn (string $entityId) => [
            'entity_id' => $entityId,
            'title' => 'Demanda de teste',
            'client_id' => $client->id,
            'project_id' => 'none',
            'supplier_id' => 'none',
        ];

        // Servidor recusa o entity_id antigo (inclui subdemandas: mesmo formulário)
        Livewire::test(CreateDemand::class)
            ->fillForm($dados($this->v1->id))
            ->call('create')
            ->assertHasFormErrors(['entity_id']);
        $this->assertSame(0, Demand::where('entity_id', $this->v1->id)->count());

        // Mesmo que o payload escape do select, a camada de gravação recusa
        $page = new CreateDemand;
        $metodo = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        try {
            $metodo->invoke($page, ['entity_id' => $this->v1->id, 'field_data' => []]);
            $this->fail('Era esperada ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('data.entity_id', $e->errors());
        }

        // Na vigente, cria
        Livewire::test(CreateDemand::class)
            ->fillForm($dados($v2->id))
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(1, Demand::where('entity_id', $v2->id)->count());
    }

    public function test_query_entity_id_de_versao_antiga_e_ignorada(): void
    {
        $this->actingAs($this->admin);
        $v2 = $this->service->createNewVersion($this->v1);

        Livewire::withQueryParams(['entity_id' => $this->v1->id])
            ->test(CreateDemand::class)
            ->assertFormSet(['entity_id' => null, 'process_status_id' => null]);

        Livewire::withQueryParams(['entity_id' => $v2->id])
            ->test(CreateDemand::class)
            ->assertFormSet(['entity_id' => $v2->id]);
    }

    public function test_filtros_listam_todas_as_versoes_com_rotulo_quando_ha_mais_de_uma(): void
    {
        $this->actingAs($this->admin);
        $unico = CustomEntity::factory()->create(['name' => 'Processo Único']);

        $this->assertSame('Processo Único', CustomEntity::versionedFilterOptions()[$unico->id]);
        $this->assertSame('VER - Processo Versionado', CustomEntity::versionedFilterOptions()[$this->v1->id]);

        $v2 = $this->service->createNewVersion($this->v1);
        $opcoes = CustomEntity::versionedFilterOptions();

        $this->assertSame('VER - Processo Versionado v1 (anterior)', $opcoes[$this->v1->id]);
        $this->assertSame('VER - Processo Versionado v2 (vigente)', $opcoes[$v2->id]);
        $this->assertSame('Processo Único', $opcoes[$unico->id]);

        Livewire::test(DemandReports::class)->assertSuccessful();
    }

    // ------------------------------------------------------------------
    // Trava de exclusão
    // ------------------------------------------------------------------

    public function test_exclusao_bloqueada_no_model_com_demanda_pendente_inclusive_rascunho(): void
    {
        foreach ([DemandStatusEnum::DRAFT, DemandStatusEnum::ACTIVE, DemandStatusEnum::COMPLETED] as $status) {
            $entity = CustomEntity::factory()->create();
            $this->demanda($entity, $status);

            $this->assertTrue($this->service->hasPendingDemands($entity), "{$status->value} conta como pendente");

            try {
                $entity->delete();
                $this->fail("Era esperada exceção com demanda {$status->value}");
            } catch (ProcessDeletionBlockedException $e) {
                $this->assertSame(ProcessVersioningService::DELETION_BLOCKED_MESSAGE, $e->getMessage());
            }

            $this->assertNotNull(CustomEntity::find($entity->id));
        }
    }

    public function test_exclusao_liberada_quando_todas_as_demandas_estao_encerradas(): void
    {
        $entity = CustomEntity::factory()->create();
        $this->demanda($entity, DemandStatusEnum::CLOSED);
        $this->demanda($entity, DemandStatusEnum::CANCELED);
        $this->demanda($entity, DemandStatusEnum::EVALUATED);

        $this->assertFalse($this->service->hasPendingDemands($entity));
        $this->assertTrue($this->service->hasDemands($entity));

        $entity->delete();
        $this->assertNull(CustomEntity::find($entity->id));

        // Sem nenhuma demanda também libera
        $vazio = CustomEntity::factory()->create();
        $vazio->delete();
        $this->assertNull(CustomEntity::find($vazio->id));
    }

    public function test_exclusao_bloqueada_vale_tambem_para_force_delete(): void
    {
        $entity = CustomEntity::factory()->create();
        $this->demanda($entity, DemandStatusEnum::DRAFT);

        $this->expectException(ProcessDeletionBlockedException::class);
        $entity->forceDelete();
    }

    public function test_acoes_de_exclusao_da_tela_respeitam_a_trava(): void
    {
        $this->actingAs($this->admin);
        $bloqueado = CustomEntity::factory()->create(['name' => 'Bloqueado']);
        $this->demanda($bloqueado, DemandStatusEnum::DRAFT);
        $liberado = CustomEntity::factory()->create(['name' => 'Liberado']);
        $this->demanda($liberado, DemandStatusEnum::CLOSED);

        // Lista: ação de linha
        Livewire::test(ListCustomEntities::class)
            ->callTableAction('delete', $bloqueado);
        $this->assertNotNull(CustomEntity::find($bloqueado->id));

        // Lista: em lote, um bloqueado cancela todos
        Livewire::test(ListCustomEntities::class)
            ->callTableBulkAction('delete', [$bloqueado, $liberado]);
        $this->assertNotNull(CustomEntity::find($bloqueado->id));
        $this->assertNotNull(CustomEntity::find($liberado->id));

        // Edição: ação do cabeçalho
        Livewire::test(EditCustomEntity::class, ['record' => $bloqueado->id])
            ->callAction('delete');
        $this->assertNotNull(CustomEntity::find($bloqueado->id));

        // Liberados saem normalmente
        Livewire::test(ListCustomEntities::class)->callTableAction('delete', $liberado);
        $this->assertNull(CustomEntity::find($liberado->id));

        Livewire::test(EditCustomEntity::class, ['record' => $bloqueado->id])->assertSuccessful();
        Demand::where('entity_id', $bloqueado->id)->update(['status' => DemandStatusEnum::CLOSED->value]);
        Livewire::test(EditCustomEntity::class, ['record' => $bloqueado->id])->callAction('delete');
        $this->assertNull(CustomEntity::find($bloqueado->id));
    }

    public function test_apagar_a_versao_vigente_devolve_a_vigencia_para_a_anterior(): void
    {
        $this->demanda($this->v1, DemandStatusEnum::CLOSED);
        $v2 = $this->service->createNewVersion($this->v1);
        $this->assertFalse($this->v1->fresh()->is_current);

        $v2->delete();

        $this->assertTrue($this->v1->fresh()->is_current);
    }

    public function test_apagar_v2_e_depois_v3_deixa_a_v1_vigente(): void
    {
        $v2 = $this->service->createNewVersion($this->v1);
        $v3 = $this->service->createNewVersion($v2);

        $v2->fresh()->delete();
        $this->assertTrue($v3->fresh()->is_current, 'Apagar uma versão antiga não muda a vigente');

        $v3->fresh()->delete();

        $this->assertTrue($this->v1->fresh()->is_current);
        $this->assertSame(1, CustomEntity::where('root_entity_id', $this->v1->id)->current()->count());
    }

    public function test_apagar_a_vigente_promove_a_maior_versao_restante(): void
    {
        $v2 = $this->service->createNewVersion($this->v1);
        $v3 = $this->service->createNewVersion($v2);
        $v4 = $this->service->createNewVersion($v3);

        // v3 sai do caminho; ao apagar a v4, a maior restante (v2) volta, não a v1
        $v3->fresh()->delete();
        $v4->fresh()->delete();

        $this->assertTrue($v2->fresh()->is_current);
        $this->assertFalse($this->v1->fresh()->is_current);
    }

    public function test_apagar_versao_nao_gera_duas_vigentes_quando_outra_ja_e_vigente(): void
    {
        $v2 = $this->service->createNewVersion($this->v1);

        // Simula a corrida com "Editar estrutura": a v1 já voltou a ser vigente no banco
        // enquanto a instância da v2 ainda acredita que é a vigente
        CustomEntity::whereKey($this->v1->id)->update(['is_current' => true]);
        $this->assertTrue($v2->is_current);

        $v2->delete();

        $this->assertSame(1, CustomEntity::where('root_entity_id', $this->v1->id)->current()->count());
        $this->assertTrue($this->v1->fresh()->is_current);
    }

    public function test_apagar_a_unica_versao_nao_deixa_nada_vigente_nem_falha(): void
    {
        $this->v1->delete();

        $this->assertSame(0, CustomEntity::where('root_entity_id', $this->v1->id)->current()->count());
    }

    public function test_proximo_numero_de_versao_considera_as_excluidas(): void
    {
        $v2 = $this->service->createNewVersion($this->v1);
        $v3 = $this->service->createNewVersion($v2);
        $this->assertSame(4, $this->service->nextVersionNumber($v3->fresh()));

        // v3 excluída, v2 volta a vigente: a próxima é v4 (não v3), como o serviço grava
        $v3->fresh()->delete();
        $v2 = $v2->fresh();
        $this->assertTrue($v2->is_current);
        $this->assertSame(4, $this->service->nextVersionNumber($v2));

        $v4 = $this->service->createNewVersion($v2);
        $this->assertSame(4, $v4->version);
    }

    public function test_modal_de_editar_estrutura_mostra_o_numero_real_da_proxima_versao(): void
    {
        $this->actingAs($this->admin);
        $this->demanda($this->v1, DemandStatusEnum::CLOSED);
        $v2 = $this->service->createNewVersion($this->v1);
        $this->demanda($v2, DemandStatusEnum::CLOSED);
        $v3 = $this->service->createNewVersion($v2);
        $v3->delete();

        Livewire::test(EditCustomEntity::class, ['record' => $v2->id])
            ->mountAction('edit_structure')
            ->assertSee('Será criada a versão v4');
    }

    // ------------------------------------------------------------------
    // Telas de versões inativas
    // ------------------------------------------------------------------

    public function test_listagem_de_processos_mostra_so_vigentes_e_leva_as_versoes_inativas(): void
    {
        $this->actingAs($this->admin);
        $v2 = $this->service->createNewVersion($this->v1);
        $outro = CustomEntity::factory()->create();

        Livewire::test(ListCustomEntities::class)
            ->assertCanSeeTableRecords([$v2, $outro])
            ->assertCanNotSeeTableRecords([$this->v1->fresh()])
            ->assertActionVisible('inactive_versions')
            ->assertSee("{$this->v1->code} · v2", false);

        Livewire::test(ListInactiveCustomEntityVersions::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->v1->fresh()])
            ->assertCanNotSeeTableRecords([$v2, $outro])
            ->assertTableActionHidden('edit', $this->v1->fresh());

        $this->get(CustomEntityResource::getUrl('inactive-versions'))->assertOk();
    }

    public function test_versoes_inativas_de_um_processo_filtram_pela_raiz(): void
    {
        $this->actingAs($this->admin);
        $v2 = $this->service->createNewVersion($this->v1);

        $outroProcesso = CustomEntity::factory()->create();
        $outroV2 = $this->service->createNewVersion($outroProcesso);

        Livewire::withQueryParams(['processo' => $this->v1->id])
            ->test(ListInactiveCustomEntityVersions::class)
            ->assertCanSeeTableRecords([$this->v1->fresh()])
            ->assertCanNotSeeTableRecords([$outroProcesso->fresh()]);

        // Links nas páginas de view e edit só aparecem quando há versões antigas
        Livewire::test(ViewCustomEntity::class, ['record' => $v2->id])->assertActionVisible('inactive_versions');
        Livewire::test(EditCustomEntity::class, ['record' => $v2->id])->assertActionVisible('inactive_versions');
        Livewire::test(ViewCustomEntity::class, ['record' => $outroV2->id])->assertActionVisible('inactive_versions');

        $semHistorico = CustomEntity::factory()->create();
        Livewire::test(ViewCustomEntity::class, ['record' => $semHistorico->id])->assertActionHidden('inactive_versions');
        Livewire::test(EditCustomEntity::class, ['record' => $semHistorico->id])->assertActionHidden('inactive_versions');

        $this->get(CustomEntityResource::getUrl('inactive-versions', ['processo' => $this->v1->id]))->assertOk();
    }

    public function test_macroprocesso_lista_vigentes_e_secao_de_versoes_antigas(): void
    {
        $this->actingAs($this->admin);

        // Sem versões antigas a seção não aparece
        Livewire::test(ViewMacroprocess::class, ['record' => $this->macro->id])
            ->assertSee('Processos agrupados neste macroprocesso:')
            ->assertDontSee('Versões antigas de processos');

        $v2 = $this->service->createNewVersion($this->v1);

        $this->assertSame([$v2->id], $this->macro->currentProcesses()->pluck('id')->all());
        $this->assertSame([$this->v1->id], $this->macro->inactiveProcessVersions()->pluck('id')->all());

        Livewire::test(ViewMacroprocess::class, ['record' => $this->macro->id])
            ->assertSuccessful()
            ->assertSee('Versões antigas de processos')
            ->assertSee('VER - Processo Versionado (v2)')
            ->assertSee('VER - Processo Versionado');
    }

    // ------------------------------------------------------------------
    // Revisão do PR: busca por número, consultas e listagens por versão
    // ------------------------------------------------------------------

    public function test_busca_pelo_numero_exibido_encontra_a_versao_vigente(): void
    {
        $this->actingAs($this->admin);
        $v2 = $this->service->createNewVersion($this->v1);
        $outro = CustomEntity::factory()->create();

        // O código interno do clone não aparece na tela; busca pelo código da raiz...
        $this->assertNotSame($this->v1->code, $v2->code);
        Livewire::test(ListCustomEntities::class)
            ->searchTable($this->v1->code)
            ->assertCanSeeTableRecords([$v2])
            ->assertCanNotSeeTableRecords([$outro]);

        // ...e pelo número exatamente como é exibido
        Livewire::test(ListCustomEntities::class)
            ->searchTable($v2->display_code)
            ->assertCanSeeTableRecords([$v2])
            ->assertCanNotSeeTableRecords([$outro]);

        // A ordenação pelo número não quebra
        Livewire::test(ListCustomEntities::class)
            ->sortTable('code')
            ->assertCanSeeTableRecords([$v2, $outro]);
    }

    public function test_aba_campos_faz_consulta_constante_em_demandas(): void
    {
        $this->actingAs($this->admin);
        $this->demanda($this->v1);
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $this->v1->fresh(), 'pageClass' => EditCustomEntity::class])
                ->assertSee(ProcessVersioningService::LOCKED_STRUCTURE_MESSAGE);
            $total = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "demands"'))->count();
            DB::disableQueryLog();

            return $total;
        };

        $comPoucosCampos = $contar();

        foreach (CustomField::factory()->count(13)->create() as $i => $campo) {
            $this->v1->fields()->attach($campo->id, ['field_order' => 10 + $i]);
        }
        $this->assertSame(15, $this->v1->fields()->count());

        $comMuitosCampos = $contar();

        $this->assertSame($comPoucosCampos, $comMuitosCampos, 'Consultas em demands não podem crescer com as linhas');
        $this->assertLessThanOrEqual(3, $comMuitosCampos);
    }

    public function test_tela_do_usuario_lista_so_processos_vigentes_que_ele_gerencia(): void
    {
        $this->actingAs($this->admin);
        $v2 = $this->service->createNewVersion($this->v1);

        $this->assertCount(2, $this->gestor->managedProcesses()->get(), 'O clone copia o gestor');

        Livewire::test(ViewUser::class, ['record' => $this->gestor->id])
            ->assertSuccessful()
            ->assertSee('Processo Versionado')
            ->assertDontSee('Processo Versionado, Processo Versionado');

        // clearManagement segue sem filtro: limpa o gestor de todas as versões
        $this->gestor->clearManagement();
        $this->assertNull($this->v1->fresh()->manager_id);
        $this->assertNull($v2->fresh()->manager_id);
    }

    public function test_tela_do_papel_lista_cada_processo_uma_vez_pela_versao_vigente(): void
    {
        $this->actingAs($this->admin);
        $v2 = $this->service->createNewVersion($this->v1);

        // v1 e v2 têm o mesmo membro com o papel; só a vigente aparece, com link para ela
        Livewire::test(ViewProcessRole::class, ['record' => $this->papel->id])
            ->assertSuccessful()
            ->assertSeeHtml(CustomEntityResource::getUrl('edit', ['record' => $v2]))
            ->assertDontSeeHtml(CustomEntityResource::getUrl('edit', ['record' => $this->v1]));
    }
}
