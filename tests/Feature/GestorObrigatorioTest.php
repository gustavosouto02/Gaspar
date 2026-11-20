<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomEntityResource\Pages\CreateCustomEntity;
use App\Filament\Resources\CustomRecordTypeResource\Pages\CreateCustomRecordType;
use App\Filament\Resources\CustomRecordTypeResource\Pages\EditCustomRecordType;
use App\Filament\Resources\MacroprocessResource\Pages\CreateMacroprocess;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\CustomEntity;
use App\Models\CustomRecordType;
use App\Models\Macroprocess;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_cadastro_exige_ao_menos_um_gestor_na_criacao(): void
    {
        $this->actingAs($this->admin);

        foreach ([null, []] as $vazio) {
            Livewire::test(CreateCustomRecordType::class)
                ->fillForm([
                    'name' => 'Produtos',
                    'slug' => 'produtos',
                    'managers' => $vazio,
                ])
                ->call('create')
                ->assertHasFormErrors(['managers' => 'required']);
        }

        $this->assertDatabaseMissing('custom_record_types', ['slug' => 'produtos']);
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

    public function test_cadastro_antigo_sem_gestor_exige_gestor_ao_editar(): void
    {
        $this->actingAs($this->admin);

        $tipo = $this->cadastro('Legado');

        Livewire::test(EditCustomRecordType::class, ['record' => $tipo->id])
            ->call('save')
            ->assertHasFormErrors(['managers' => 'required']);

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

    public function test_excluir_gestor_zera_manager_id_sem_apagar_registros(): void
    {
        $macro = $this->macroprocesso(['manager_id' => $this->gestor->id]);

        // FK nullOnDelete: força a exclusão física (User usa SoftDeletes).
        $this->gestor->forceDelete();

        $this->assertNull($macro->fresh()->manager_id);
    }

    public function test_excluir_gestor_remove_so_o_vinculo_do_cadastro(): void
    {
        $outroGestor = $this->gestorAtivo('Gestor Dois');
        $tipo = $this->cadastro();
        $tipo->managers()->attach([$this->gestor->id, $outroGestor->id]);

        // FK cascadeOnDelete da pivô: força a exclusão física (User usa SoftDeletes).
        $this->gestor->forceDelete();

        $this->assertNotNull($tipo->fresh());
        $this->assertEquals([$outroGestor->id], $tipo->managers()->pluck('users.id')->all());
        $this->assertDatabaseMissing('custom_record_type_manager', ['user_id' => $this->gestor->id]);
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
