<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomEntityResource\Pages\CreateCustomEntity;
use App\Filament\Resources\CustomEntityResource\Pages\EditCustomEntity;
use App\Filament\Resources\CustomRecordTypeResource\Pages\CreateCustomRecordType;
use App\Filament\Resources\CustomRecordTypeResource\Pages\EditCustomRecordType;
use App\Filament\Resources\MacroprocessResource\Pages\CreateMacroprocess;
use App\Filament\Resources\MacroprocessResource\Pages\EditMacroprocess;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\ActivityLog;
use App\Models\CustomEntity;
use App\Models\CustomRecordType;
use App\Models\Macroprocess;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class GestorObrigatorioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'user_role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $this->gestor = User::factory()->create([
            'name' => 'Gestora Ativa',
            'user_role' => UserRoleEnum::GESTOR,
            'is_active' => true,
        ]);
    }

    private function macroprocesso(array $attributes = []): Macroprocess
    {
        return Macroprocess::create(array_merge([
            'name' => 'Macro Gestor',
            'acronym' => 'MGE',
            'value_chain_function' => 'Suportar Processos',
            'description' => 'Descrição',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    private function processo(Macroprocess $macro, array $attributes = []): CustomEntity
    {
        return CustomEntity::create(array_merge([
            'name' => 'Processo Gestor',
            'macroprocess_id' => $macro->id,
            'purpose' => 'Apoio',
            'sla_hours' => 24,
            'description' => 'Descrição',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    private function gestorAtivo(string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'user_role' => UserRoleEnum::GESTOR,
            'is_active' => true,
        ]);
    }

    private function cadastro(string $nome = 'Produtos'): CustomRecordType
    {
        return CustomRecordType::create([
            'name' => $nome,
            'slug' => str($nome)->slug()->toString(),
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    public function test_cadastro_pode_ser_criado_sem_gestor(): void
    {
        $this->actingAs($this->admin);

        foreach ([null, []] as $i => $vazio) {
            Livewire::test(CreateCustomRecordType::class)
                ->fillForm([
                    'name' => "Produtos {$i}",
                    'slug' => "produtos-{$i}",
                    'managers' => $vazio,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $tipo = CustomRecordType::where('slug', "produtos-{$i}")->firstOrFail();
            $this->assertCount(0, $tipo->managers()->get());
        }
    }

    public function test_cadastro_eh_salvo_com_um_gestor(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomRecordType::class)
            ->fillForm([
                'name' => 'Produtos',
                'slug' => 'produtos',
                'managers' => [$this->gestor->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tipo = CustomRecordType::where('slug', 'produtos')->firstOrFail();

        $this->assertEquals([$this->gestor->id], $tipo->managers()->pluck('users.id')->all());
    }

    public function test_cadastro_aceita_dois_ou_mais_gestores(): void
    {
        $this->actingAs($this->admin);

        $outro = $this->gestorAtivo('Gestor Dois');
        $terceiro = $this->gestorAtivo('Gestor Tres');

        Livewire::test(CreateCustomRecordType::class)
            ->fillForm([
                'name' => 'Produtos',
                'slug' => 'produtos',
                'managers' => [$this->gestor->id, $outro->id, $terceiro->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tipo = CustomRecordType::where('slug', 'produtos')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$this->gestor->id, $outro->id, $terceiro->id],
            $tipo->managers()->pluck('users.id')->all()
        );
    }

    public function test_cadastro_antigo_sem_gestor_pode_ser_salvo_na_edicao_e_aceita_gestor(): void
    {
        $this->actingAs($this->admin);

        $tipo = $this->cadastro('Legado');

        // Edição: gestor opcional ("Sem gestor"); obrigatório só na criação
        Livewire::test(EditCustomRecordType::class, ['record' => $tipo->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertCount(0, $tipo->fresh()->managers);

        Livewire::test(EditCustomRecordType::class, ['record' => $tipo->id])
            ->set('data.managers', [$this->gestor->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($tipo->fresh()->managers->contains($this->gestor));
    }

    public function test_editar_cadastro_permite_acrescentar_e_trocar_gestores(): void
    {
        $this->actingAs($this->admin);

        $outro = $this->gestorAtivo('Gestor Dois');
        $tipo = $this->cadastro();
        $tipo->managers()->attach($this->gestor->id);

        Livewire::test(EditCustomRecordType::class, ['record' => $tipo->id])
            ->assertFormSet(['managers' => [$this->gestor->id]])
            ->set('data.managers', [$this->gestor->id, $outro->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            [$this->gestor->id, $outro->id],
            $tipo->managers()->pluck('users.id')->all()
        );

        Livewire::test(EditCustomRecordType::class, ['record' => $tipo->id])
            ->set('data.managers', [$outro->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals([$outro->id], $tipo->managers()->pluck('users.id')->all());
    }

    public function test_cadastro_rejeita_gestor_inativo_ou_sem_perfil_gestor_mesmo_junto_de_um_valido(): void
    {
        $this->actingAs($this->admin);

        $gestorInativo = User::factory()->create([
            'user_role' => UserRoleEnum::GESTOR,
            'is_active' => false,
        ]);
        $executor = User::factory()->create([
            'user_role' => UserRoleEnum::EXECUTOR,
            'is_active' => true,
        ]);

        foreach ([$gestorInativo, $executor, $this->admin] as $invalido) {
            Livewire::test(CreateCustomRecordType::class)
                ->fillForm([
                    'name' => 'Produtos',
                    'slug' => 'produtos',
                    'managers' => [$this->gestor->id, $invalido->id],
                ])
                ->call('create')
                ->assertHasFormErrors(['managers.1']);
        }

        $this->assertDatabaseMissing('custom_record_types', ['slug' => 'produtos']);
    }

    public function test_macroprocesso_exige_gestor_na_criacao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateMacroprocess::class)
            ->fillForm([
                'name' => 'Gestão de Suprimentos',
                'acronym' => 'SUPRI',
                'value_chain_function' => 'Suportar Processos',
                'description' => 'Descrição',
                'is_active' => true,
                'manager_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['manager_id' => 'required']);

        $this->assertDatabaseMissing('macroprocesses', ['acronym' => 'SUPRI']);
    }

    public function test_processo_exige_gestor_na_criacao(): void
    {
        $this->actingAs($this->admin);

        $macro = $this->macroprocesso(['manager_id' => $this->gestor->id]);

        Livewire::test(CreateCustomEntity::class)
            ->fillForm([
                'name' => 'Compra Direta',
                'macroprocess_id' => $macro->id,
                'purpose' => 'Apoio',
                'sla_hours' => 72,
                'description' => 'Descrição',
                'is_active' => true,
                'manager_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['manager_id' => 'required']);

        $this->assertDatabaseMissing('custom_entities', ['name' => 'Compra Direta']);
    }

    public function test_seletor_de_gestor_lista_apenas_usuarios_gestor_ativos(): void
    {
        $this->actingAs($this->admin);

        $gestorInativo = User::factory()->create([
            'name' => 'Gestor Inativo',
            'user_role' => UserRoleEnum::GESTOR,
            'is_active' => false,
        ]);
        $executor = User::factory()->create([
            'name' => 'Executor Qualquer',
            'user_role' => UserRoleEnum::EXECUTOR,
            'is_active' => true,
        ]);

        $paginas = [
            CreateCustomRecordType::class => 'managers',
            CreateMacroprocess::class => 'manager_id',
            CreateCustomEntity::class => 'manager_id',
        ];

        foreach ($paginas as $pagina => $campo) {
            Livewire::test($pagina)
                ->assertFormFieldExists($campo, function (Select $field) use ($gestorInativo, $executor): bool {
                    $opcoes = $field->getSearchResults('');

                    return in_array('Gestora Ativa', $opcoes, true)
                        && ! in_array($gestorInativo->name, $opcoes, true)
                        && ! in_array($executor->name, $opcoes, true)
                        && ! in_array($this->admin->name, $opcoes, true);
                });
        }
    }

    public function test_macroprocesso_e_processo_tem_gestor_unico_e_cadastro_aceita_varios(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateMacroprocess::class)
            ->assertFormFieldExists('manager_id', fn (Select $field): bool => ! $field->isMultiple());
        Livewire::test(CreateCustomEntity::class)
            ->assertFormFieldExists('manager_id', fn (Select $field): bool => ! $field->isMultiple());
        Livewire::test(CreateCustomRecordType::class)
            ->assertFormFieldExists('managers', fn (Select $field): bool => $field->isMultiple());
    }

    public function test_macroprocesso_nao_aceita_mais_de_um_gestor_nem_nao_gestor(): void
    {
        $this->actingAs($this->admin);

        $outro = $this->gestorAtivo('Gestor Dois');
        $executor = User::factory()->create(['user_role' => UserRoleEnum::EXECUTOR, 'is_active' => true]);

        $dados = [
            'name' => 'Gestão de Suprimentos',
            'acronym' => 'SUPRI',
            'value_chain_function' => 'Suportar Processos',
            'description' => 'Descrição',
            'is_active' => true,
        ];

        // Mais de um gestor (array no lugar do valor único)
        Livewire::test(CreateMacroprocess::class)
            ->fillForm($dados + ['manager_id' => [$this->gestor->id, $outro->id]])
            ->call('create')
            ->assertHasFormErrors(['manager_id']);

        // Usuário sem perfil GESTOR
        Livewire::test(CreateMacroprocess::class)
            ->fillForm($dados + ['manager_id' => $executor->id])
            ->call('create')
            ->assertHasFormErrors(['manager_id']);

        $this->assertDatabaseMissing('macroprocesses', ['acronym' => 'SUPRI']);
    }

    public function test_processo_nao_aceita_mais_de_um_gestor_nem_nao_gestor(): void
    {
        $this->actingAs($this->admin);

        $macro = $this->macroprocesso(['manager_id' => $this->gestor->id]);
        $outro = $this->gestorAtivo('Gestor Dois');
        $executor = User::factory()->create(['user_role' => UserRoleEnum::EXECUTOR, 'is_active' => true]);

        $dados = [
            'name' => 'Compra Direta',
            'macroprocess_id' => $macro->id,
            'purpose' => 'Apoio',
            'sla_hours' => 72,
            'description' => 'Descrição',
            'is_active' => true,
        ];

        Livewire::test(CreateCustomEntity::class)
            ->fillForm($dados + ['manager_id' => [$this->gestor->id, $outro->id]])
            ->call('create')
            ->assertHasFormErrors(['manager_id']);

        Livewire::test(CreateCustomEntity::class)
            ->fillForm($dados + ['manager_id' => $executor->id])
            ->call('create')
            ->assertHasFormErrors(['manager_id']);

        $this->assertDatabaseMissing('custom_entities', ['name' => 'Compra Direta']);
    }

    public function test_gestor_inativo_nao_eh_aceito_em_macroprocesso(): void
    {
        $this->actingAs($this->admin);

        $gestorInativo = User::factory()->create([
            'user_role' => UserRoleEnum::GESTOR,
            'is_active' => false,
        ]);

        Livewire::test(CreateMacroprocess::class)
            ->fillForm([
                'name' => 'Gestão de Suprimentos',
                'acronym' => 'SUPRI',
                'value_chain_function' => 'Suportar Processos',
                'description' => 'Descrição',
                'is_active' => true,
                'manager_id' => $gestorInativo->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['manager_id']);
    }

    public function test_usuario_gestor_tem_relacoes_inversas_com_o_que_gerencia(): void
    {
        $tipo = $this->cadastro('Produtos');
        $outroTipo = $this->cadastro('Parceiros');
        $outroGestor = $this->gestorAtivo('Gestor Dois');
        $tipo->managers()->attach([$this->gestor->id, $outroGestor->id]);
        $outroTipo->managers()->attach($this->gestor->id);

        $macro = $this->macroprocesso(['manager_id' => $this->gestor->id]);
        $processo = $this->processo($macro, ['manager_id' => $this->gestor->id]);
        $outroProcesso = $this->processo($macro, ['name' => 'Sem gestor']);

        // N:N: o mesmo usuário gerencia vários cadastros e o cadastro tem vários gestores
        $this->assertCount(2, $this->gestor->managedRecordTypes);
        $this->assertCount(1, $outroGestor->managedRecordTypes);
        $this->assertTrue($outroGestor->managedRecordTypes->contains($tipo));
        $this->assertFalse($outroGestor->managedRecordTypes->contains($outroTipo));
        $this->assertCount(2, $tipo->managers);
        $this->assertTrue($tipo->managers->contains($this->gestor));

        $this->assertTrue($this->gestor->managedMacroprocesses->contains($macro));
        $this->assertTrue($this->gestor->managedProcesses->contains($processo));
        $this->assertFalse($this->gestor->managedProcesses->contains($outroProcesso));

        $this->assertTrue($macro->manager->is($this->gestor));
        $this->assertTrue($processo->manager->is($this->gestor));
    }

    /**
     * Macroprocesso e processo do gestor + um cadastro que ele gerencia junto com outro gestor.
     *
     * @return array{0: Macroprocess, 1: CustomEntity, 2: CustomRecordType, 3: User}
     */
    private function cenarioDeGestao(): array
    {
        $outroGestor = $this->gestorAtivo('Gestor Dois');
        $macro = $this->macroprocesso(['manager_id' => $this->gestor->id]);
        $processo = $this->processo($macro, ['manager_id' => $this->gestor->id]);
        $tipo = $this->cadastro();
        $tipo->managers()->attach([$this->gestor->id, $outroGestor->id]);

        return [$macro, $processo, $tipo, $outroGestor];
    }

    private function assertGestorSemVinculos(Macroprocess $macro, CustomEntity $processo, CustomRecordType $tipo, User $outroGestor): void
    {
        $this->assertNull($macro->fresh()->manager_id);
        $this->assertNull($processo->fresh()->manager_id);
        $this->assertEquals([$outroGestor->id], $tipo->managers()->pluck('users.id')->all());
        $this->assertDatabaseMissing('custom_record_type_manager', ['user_id' => $this->gestor->id]);
        // Os registros continuam existindo, só ficam "Sem gestor"
        $this->assertNotNull($macro->fresh());
        $this->assertNotNull($processo->fresh());
    }

    public function test_trocar_o_perfil_do_gestor_limpa_o_vinculo(): void
    {
        [$macro, $processo, $tipo, $outroGestor] = $this->cenarioDeGestao();

        $this->gestor->update(['user_role' => UserRoleEnum::EXECUTOR]);

        $this->assertGestorSemVinculos($macro, $processo, $tipo, $outroGestor);
    }

    public function test_desativar_o_gestor_limpa_o_vinculo(): void
    {
        [$macro, $processo, $tipo, $outroGestor] = $this->cenarioDeGestao();

        $this->gestor->update(['is_active' => false]);

        $this->assertGestorSemVinculos($macro, $processo, $tipo, $outroGestor);
    }

    public function test_excluir_o_gestor_limpa_o_vinculo(): void
    {
        [$macro, $processo, $tipo, $outroGestor] = $this->cenarioDeGestao();

        // Soft delete: nullOnDelete/cascadeOnDelete da FK não disparam, o vínculo é limpo no model
        $this->gestor->delete();

        $this->assertSoftDeleted('users', ['id' => $this->gestor->id]);
        $this->assertGestorSemVinculos($macro, $processo, $tipo, $outroGestor);
    }

    public function test_limpar_a_gestao_registra_atividade_e_alcanca_itens_na_lixeira(): void
    {
        $this->actingAs($this->admin);
        $macro = $this->macroprocesso(['manager_id' => $this->gestor->id]);
        $ativo = $this->processo($macro, ['manager_id' => $this->gestor->id]);
        $naLixeira = $this->processo($macro, ['name' => 'Na lixeira', 'manager_id' => $this->gestor->id]);
        $naLixeira->delete();
        $macroNaLixeira = $this->macroprocesso(['manager_id' => $this->gestor->id]);
        $macroNaLixeira->delete();

        $this->gestor->update(['is_active' => false]);

        $this->assertNull(CustomEntity::withTrashed()->find($naLixeira->id)->manager_id);
        $this->assertNull(Macroprocess::withTrashed()->find($macroNaLixeira->id)->manager_id);
        $this->assertNull($macro->fresh()->manager_id);

        // O model registra o log de quando o processo ficou "Sem gestor", inclusive na lixeira
        foreach ([$ativo, $naLixeira] as $processo) {
            $log = ActivityLog::where('auditable_type', CustomEntity::class)
                ->where('auditable_id', (string) $processo->id)
                ->where('event', 'updated')
                ->get()
                ->first(fn ($l) => array_key_exists('manager_id', $l->new_values ?? []));

            $this->assertNotNull($log, 'sem log de "Sem gestor" para '.$processo->name);
            $this->assertNull($log->new_values['manager_id']);
            $this->assertEquals($this->gestor->id, $log->old_values['manager_id']);
        }
    }

    public function test_alterar_outros_dados_do_gestor_ou_manter_o_perfil_nao_limpa_o_vinculo(): void
    {
        [$macro, $processo, $tipo] = $this->cenarioDeGestao();

        $this->gestor->update(['name' => 'Novo Nome', 'user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);

        $this->assertEquals($this->gestor->id, $macro->fresh()->manager_id);
        $this->assertEquals($this->gestor->id, $processo->fresh()->manager_id);
        $this->assertTrue($tipo->managers->contains($this->gestor));
    }

    public function test_formulario_do_usuario_troca_perfil_e_desativa_gestor_limpando_o_vinculo(): void
    {
        $this->actingAs($this->admin);
        [$macro, $processo, $tipo, $outroGestor] = $this->cenarioDeGestao();

        Livewire::test(EditUser::class, ['record' => $this->gestor->id])
            ->fillForm(['user_role' => UserRoleEnum::EXECUTOR->value, 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertGestorSemVinculos($macro, $processo, $tipo, $outroGestor);
    }

    public function test_acao_de_excluir_do_usuario_limpa_o_vinculo(): void
    {
        $this->actingAs($this->admin);
        [$macro, $processo, $tipo, $outroGestor] = $this->cenarioDeGestao();

        Livewire::test(ListUsers::class)->callTableAction('delete', $this->gestor);

        $this->assertSoftDeleted('users', ['id' => $this->gestor->id]);
        $this->assertGestorSemVinculos($macro, $processo, $tipo, $outroGestor);
    }

    public function test_macroprocesso_e_processo_sem_gestor_exigem_gestor_na_edicao_e_cadastro_nao(): void
    {
        $this->actingAs($this->admin);
        [$macro, $processo, $tipo] = $this->cenarioDeGestao();
        $this->gestor->update(['is_active' => false]);

        Livewire::test(EditMacroprocess::class, ['record' => $macro->id])
            ->assertFormSet(['manager_id' => null])
            ->fillForm(['name' => 'Macro Renomeado'])
            ->call('save')
            ->assertHasFormErrors(['manager_id' => 'required']);
        $this->assertNotSame('Macro Renomeado', $macro->fresh()->name);

        Livewire::test(EditCustomEntity::class, ['record' => $processo->id])
            ->fillForm(['name' => 'Processo Renomeado'])
            ->call('save')
            ->assertHasFormErrors(['manager_id' => 'required']);
        $this->assertNotSame('Processo Renomeado', $processo->fresh()->name);

        // Com um gestor ativo, a edição salva
        $novo = User::factory()->create(['user_role' => UserRoleEnum::GESTOR, 'is_active' => true]);
        Livewire::test(EditMacroprocess::class, ['record' => $macro->id])
            ->fillForm(['name' => 'Macro Renomeado', 'manager_id' => $novo->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($novo->id, $macro->fresh()->manager_id);

        // Cadastro segue opcional: salva sem nenhum gestor
        $tipo->managers()->detach();
        Livewire::test(EditCustomRecordType::class, ['record' => $tipo->id])
            ->fillForm(['name' => 'Renomeado'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Renomeado', $tipo->fresh()->name);
        $this->assertCount(0, $tipo->managers()->get());
    }

    public function test_edicao_nao_deixa_remover_o_gestor_de_macroprocesso_nem_de_processo(): void
    {
        $this->actingAs($this->admin);
        $macro = $this->macroprocesso(['manager_id' => $this->gestor->id]);

        Livewire::test(EditMacroprocess::class, ['record' => $macro->id])
            ->fillForm(['manager_id' => null])
            ->call('save')
            ->assertHasFormErrors(['manager_id' => 'required']);

        $this->assertSame($this->gestor->id, $macro->fresh()->manager_id);
    }

    public function test_gestor_que_virou_executor_por_fora_do_model_sai_da_pivo_ao_salvar_o_cadastro(): void
    {
        $this->actingAs($this->admin);

        $ativo = $this->gestorAtivo('Gestor Dois');
        $tipo = $this->cadastro();
        $tipo->managers()->attach([$this->gestor->id, $ativo->id]);

        // Mudança direto no banco, sem passar pelos eventos do model (defesa em profundidade)
        DB::table('users')->where('id', $this->gestor->id)->update(['user_role' => UserRoleEnum::EXECUTOR->value]);

        // O ex-gestor continua no estado carregado, para poder ser removido
        $component = Livewire::test(EditCustomRecordType::class, ['record' => $tipo->id]);
        $this->assertContains($this->gestor->id, $component->get('data.managers'));

        $component
            ->set('data.managers', [$ativo->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals([$ativo->id], $tipo->managers()->pluck('users.id')->all());
    }

    public function test_cadastro_com_ex_gestor_no_estado_exige_remove_lo_para_salvar(): void
    {
        $this->actingAs($this->admin);

        $ativo = $this->gestorAtivo('Gestor Dois');
        $tipo = $this->cadastro();
        $tipo->managers()->attach([$this->gestor->id, $ativo->id]);
        DB::table('users')->where('id', $this->gestor->id)->update(['is_active' => false]);

        // Mantê-lo no estado é rejeitado pela validação (só gestor ativo é aceito)
        Livewire::test(EditCustomRecordType::class, ['record' => $tipo->id])
            ->call('save')
            ->assertHasFormErrors(['managers.0']);

        $this->assertCount(2, $tipo->managers()->get());
    }

    public function test_visualizacao_do_usuario_gestor_mostra_o_que_ele_gerencia(): void
    {
        $this->actingAs($this->admin);

        $macro = $this->macroprocesso(['name' => 'Macro Visivel', 'manager_id' => $this->gestor->id]);
        $this->processo($macro, ['name' => 'Processo Visivel', 'manager_id' => $this->gestor->id]);

        Livewire::test(ViewUser::class, ['record' => $this->gestor->id])
            ->assertSee('Cadastros que gerencia')
            ->assertSee('Macroprocessos que gerencia')
            ->assertSee('Processos que gerencia')
            ->assertSee('Macro Visivel')
            ->assertSee('Processo Visivel');
    }

    public function test_visualizacao_de_usuario_nao_gestor_nao_mostra_o_que_gerencia(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ViewUser::class, ['record' => $this->admin->id])
            ->assertDontSee('Macroprocessos que gerencia');
    }
}
