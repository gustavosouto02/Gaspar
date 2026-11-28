<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\CustomRecordTypeResource\Pages\CreateCustomRecordType;
use App\Filament\Resources\CustomRecordTypeResource\Pages\EditCustomRecordType;
use App\Filament\Resources\CustomRecordTypeResource\Pages\ListCustomRecordTypes;
use App\Filament\Resources\MacroprocessResource;
use App\Filament\Resources\SupplierResource;
use App\Models\Client;
use App\Models\CustomRecordType;
use App\Models\Macroprocess;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomRecordTypeValidationTest extends TestCase
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
            'user_role' => UserRoleEnum::GESTOR,
            'is_active' => true,
        ]);
    }

    public function test_criar_cadastro_com_nome_ate_15_caracteres_tem_sucesso(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomRecordType::class)
            ->set('data.name', '123456789012345') // 15 caracteres
            ->set('data.managers', [$this->gestor->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('custom_record_types', [
            'name' => '123456789012345',
            'slug' => '123456789012345',
            'is_system' => false,
        ]);
    }

    public function test_criar_cadastro_com_nome_maior_que_15_caracteres_falha_validacao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomRecordType::class)
            ->set('data.name', '1234567890123456') // 16 caracteres
            ->call('create')
            ->assertHasFormErrors(['name' => 'max']);
    }

    public function test_editar_cadastro_base_com_nome_ate_15_caracteres_tem_sucesso(): void
    {
        $this->actingAs($this->admin);

        $baseType = CustomRecordType::where('model_class', Client::class)
            ->where('is_system', true)
            ->firstOrFail();

        Livewire::test(EditCustomRecordType::class, ['record' => $baseType->id])
            ->assertFormFieldIsEnabled('name')
            ->set('data.name', 'Parceiros')
            ->set('data.managers', [$this->gestor->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('custom_record_types', [
            'id' => $baseType->id,
            'name' => 'Parceiros',
            'slug' => 'clientes', // Slug original mantido
            'is_system' => true,
        ]);

        $this->assertSame('Parceiros', ClientResource::getNavigationLabel());
    }

    public function test_editar_cadastro_base_com_nome_maior_que_15_caracteres_falha_validacao(): void
    {
        $this->actingAs($this->admin);

        $baseType = CustomRecordType::where('model_class', Client::class)
            ->where('is_system', true)
            ->firstOrFail();

        Livewire::test(EditCustomRecordType::class, ['record' => $baseType->id])
            ->set('data.name', 'NomeComMaisDe15C') // 16 caracteres
            ->call('save')
            ->assertHasFormErrors(['name' => 'max']);
    }

    public function test_slug_fica_oculto_no_formulario_e_na_listagem(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomRecordType::class)
            ->assertFormFieldDoesNotExist('slug');

        Livewire::test(ListCustomRecordTypes::class)
            ->assertTableColumnDoesNotExist('slug');
    }

    public function test_slug_eh_gerado_do_nome_e_nao_muda_ao_renomear(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomRecordType::class)
            ->set('data.name', 'Veículos')
            ->call('create')
            ->assertHasNoFormErrors();

        $tipo = CustomRecordType::where('name', 'Veículos')->firstOrFail();
        $this->assertSame('veiculos', $tipo->slug);

        Livewire::test(EditCustomRecordType::class, ['record' => $tipo->id])
            ->set('data.name', 'Frota')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('veiculos', $tipo->fresh()->slug);

        // O nome antigo ficou livre, mas o slug dele continua ocupado.
        Livewire::test(CreateCustomRecordType::class)
            ->set('data.name', 'Veículos')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('custom_record_types', ['name' => 'Veículos', 'slug' => 'veiculos-2']);
    }

    public function test_listagem_pesquisa_pelo_slug_mesmo_sem_mostra_lo(): void
    {
        $this->actingAs($this->admin);

        $renomeado = CustomRecordType::create(['name' => 'Frota', 'slug' => 'veiculos']);
        $outro = CustomRecordType::create(['name' => 'Produtos', 'slug' => 'produtos']);

        Livewire::test(ListCustomRecordTypes::class)
            ->searchTable('veiculos')
            ->assertCanSeeTableRecords([$renomeado])
            ->assertCanNotSeeTableRecords([$outro]);
    }

    public function test_nome_repetido_ou_sem_letras_e_numeros_eh_recusado(): void
    {
        $this->actingAs($this->admin);

        CustomRecordType::create(['name' => 'Produtos', 'slug' => 'produtos']);

        Livewire::test(CreateCustomRecordType::class)
            ->set('data.name', 'Produtos')
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);

        Livewire::test(CreateCustomRecordType::class)
            ->set('data.name', '---')
            ->call('create')
            ->assertHasFormErrors(['name' => 'regex']);
    }

    public function test_cadastro_legado_com_nome_longo_pode_ser_editado_quando_nome_nao_muda(): void
    {
        $this->actingAs($this->admin);

        $legacyType = CustomRecordType::create([
            'name' => 'NomeMuitoLongoLegado20Chars',
            'slug' => 'nome-muito-longo',
            'is_active' => true,
            'is_system' => false,
        ]);

        Livewire::test(EditCustomRecordType::class, ['record' => $legacyType->id])
            ->set('data.is_active', false)
            ->set('data.managers', [$this->gestor->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('custom_record_types', [
            'id' => $legacyType->id,
            'is_active' => false,
        ]);
    }

    public function test_fallback_retorna_label_padrao_quando_registro_de_sistema_nao_existe(): void
    {
        CustomRecordType::where('model_class', Supplier::class)->delete();

        $this->assertSame('Fornecedores', SupplierResource::getNavigationLabel());
        $this->assertSame('Fornecedores', SupplierResource::getPluralModelLabel());
    }

    public function test_macroprocess_navigation_items_refletem_novo_nome(): void
    {
        $this->actingAs($this->admin);

        $baseType = CustomRecordType::where('model_class', Macroprocess::class)
            ->where('is_system', true)
            ->firstOrFail();

        $baseType->update(['name' => 'Processos Mae']);

        $items = MacroprocessResource::getNavigationItems();
        $labels = array_map(fn ($item) => $item->getLabel(), $items);

        $this->assertContains('Processos Mae', $labels);
    }

    public function test_lista_de_cadastros_nao_mostra_macroprocessos(): void
    {
        $this->actingAs($this->admin);

        $macro = CustomRecordType::where('model_class', Macroprocess::class)->firstOrFail();
        $cliente = CustomRecordType::where('model_class', Client::class)->firstOrFail();
        $customizado = CustomRecordType::create(['name' => 'Veículos', 'slug' => 'veiculos']);

        Livewire::test(ListCustomRecordTypes::class)
            ->assertCanSeeTableRecords([$cliente, $customizado])
            ->assertCanNotSeeTableRecords([$macro]);
    }
}
