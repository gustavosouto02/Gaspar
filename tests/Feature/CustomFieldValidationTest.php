<?php

namespace Tests\Feature;

use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomFieldResource\Pages\CreateCustomField;
use App\Filament\Resources\CustomFieldResource\Pages\EditCustomField;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomFieldValidationTest extends TestCase
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

    public function test_chave_slug_eh_bloqueada_e_gerada_automaticamente_a_partir_do_nome(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(CreateCustomField::class)
            ->assertFormFieldIsDisabled('key')
            ->set('data.name', 'Prazo de Entrega Estimado')
            ->set('data.field_type', FieldTypeEnum::TEXT->value)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('custom_fields', [
            'name' => 'Prazo de Entrega Estimado',
            'key' => 'prazo_de_entrega_estimado',
            'field_type' => FieldTypeEnum::TEXT->value,
        ]);
    }

    public function test_chave_slug_permanece_bloqueada_na_edicao(): void
    {
        $this->actingAs($this->admin);

        $field = CustomField::create([
            'name' => 'Data Limite',
            'key' => 'data_limite',
            'field_type' => FieldTypeEnum::DATE->value,
            'is_required' => false,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(EditCustomField::class, ['record' => $field->id])
            ->assertFormFieldIsDisabled('key');
    }

    public function test_campo_do_tipo_moeda_pode_ser_criado_no_formulario(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCustomField::class)
            ->set('data.name', 'Valor Estimado do Contrato')
            ->set('data.field_type', FieldTypeEnum::CURRENCY->value)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('custom_fields', [
            'name' => 'Valor Estimado do Contrato',
            'key' => 'valor_estimado_do_contrato',
            'field_type' => FieldTypeEnum::CURRENCY->value,
        ]);
    }
}
