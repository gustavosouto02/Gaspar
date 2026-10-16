<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomEntityResource\Pages\CreateCustomEntity;
use App\Filament\Resources\MacroprocessResource\Pages\CreateMacroprocess;
use App\Models\CustomEntity;
use App\Models\Macroprocess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MacroprocessoAndProcessoValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'user_role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_campos_bases_de_macroprocesso_sao_obrigatorios_na_criacao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateMacroprocess::class)
            ->fillForm([
                'name' => '',
                'acronym' => '',
                'value_chain_function' => null,
                'description' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'acronym' => 'required',
                'value_chain_function' => 'required',
                'description' => 'required',
            ]);
    }

    public function test_macroprocesso_eh_criado_com_sucesso_quando_campos_bases_sao_preenchidos(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateMacroprocess::class)
            ->fillForm([
                'name' => 'Gestão de Suprimentos',
                'acronym' => 'SUPRI',
                'value_chain_function' => 'Suportar Processos',
                'description' => 'Macroprocesso responsável pela aquisição e distribuição de insumos.',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('macroprocesses', [
            'name' => 'Gestão de Suprimentos',
            'acronym' => 'SUPRI',
            'value_chain_function' => 'Suportar Processos',
        ]);
    }

    public function test_campos_bases_de_processo_sao_obrigatorios_na_criacao(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomEntity::class)
            ->fillForm([
                'name' => '',
                'macroprocess_id' => null,
                'purpose' => null,
                'sla_hours' => null,
                'description' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'macroprocess_id' => 'required',
                'purpose' => 'required',
                'sla_hours' => 'required',
                'description' => 'required',
            ]);
    }

    public function test_processo_eh_criado_com_sucesso_quando_campos_bases_sao_preenchidos(): void
    {
        $this->actingAs($this->admin);

        $macro = Macroprocess::create([
            'name' => 'Macroprocesso Teste',
            'acronym' => 'TEST',
            'value_chain_function' => 'Apoio',
            'description' => 'Descrição do macroprocesso teste',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(CreateCustomEntity::class)
            ->fillForm([
                'name' => 'Compra Direta de Pequeno Valor',
                'macroprocess_id' => $macro->id,
                'purpose' => 'Apoio',
                'sla_hours' => 72,
                'description' => 'Fluxo de aquisições até o limite de dispensa.',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('custom_entities', [
            'name' => 'Compra Direta de Pequeno Valor',
            'macroprocess_id' => $macro->id,
            'purpose' => 'Apoio',
            'sla_hours' => 72,
        ]);
    }
}
