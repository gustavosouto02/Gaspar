<?php

namespace Tests\Unit;

use App\Enums\FieldTypeEnum;
use App\Models\CustomField;
use App\Services\CustomFieldComponentFactory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Tests\TestCase;

class CustomFieldComponentFactoryTest extends TestCase
{
    private CustomFieldComponentFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new CustomFieldComponentFactory;
    }

    private function field(FieldTypeEnum $type, array $attributes = []): CustomField
    {
        return new CustomField(array_merge([
            'name' => 'Campo de teste',
            'key' => 'campo_teste',
            'field_type' => $type,
        ], $attributes));
    }

    public function test_text_gera_text_input(): void
    {
        $component = $this->factory->make($this->field(FieldTypeEnum::TEXT), 'data.campo_teste');

        $this->assertInstanceOf(TextInput::class, $component);
        $this->assertFalse($component->isNumeric());
    }

    public function test_textarea_gera_textarea_com_3_linhas(): void
    {
        $component = $this->factory->make($this->field(FieldTypeEnum::TEXTAREA), 'data.campo_teste');

        $this->assertInstanceOf(Textarea::class, $component);
        $this->assertSame(3, $component->getRows());
    }

    public function test_number_gera_text_input_numerico(): void
    {
        $component = $this->factory->make($this->field(FieldTypeEnum::NUMBER), 'data.campo_teste');

        $this->assertInstanceOf(TextInput::class, $component);
        $this->assertTrue($component->isNumeric());
    }

    public function test_currency_gera_campo_de_moeda(): void
    {
        $component = $this->factory->make($this->field(FieldTypeEnum::CURRENCY), 'data.campo_teste');

        $this->assertInstanceOf(TextInput::class, $component);
        // MoneyInput::make devolve um TextInput com prefixo e máscara pt-BR
        $this->assertSame('R$', $component->getPrefixLabel());
        $this->assertSame('0,00', $component->getPlaceholder());
        $this->assertNotNull($component->getMask());
    }

    public function test_date_gera_date_picker(): void
    {
        $component = $this->factory->make($this->field(FieldTypeEnum::DATE), 'data.campo_teste');

        $this->assertInstanceOf(DatePicker::class, $component);
    }

    public function test_select_gera_select_com_opcoes_do_options_json(): void
    {
        $component = $this->factory->make(
            $this->field(FieldTypeEnum::SELECT, ['options_json' => ['A', 'B']]),
            'data.campo_teste'
        );

        $this->assertInstanceOf(Select::class, $component);
        $this->assertSame(['A' => 'A', 'B' => 'B'], $component->getOptions());
    }

    public function test_select_sem_opcoes_fica_vazio(): void
    {
        $component = $this->factory->make($this->field(FieldTypeEnum::SELECT), 'data.campo_teste');

        $this->assertSame([], $component->getOptions());
    }

    public function test_radio_gera_radio_com_opcoes_do_options_json(): void
    {
        $component = $this->factory->make(
            $this->field(FieldTypeEnum::RADIO, ['options_json' => ['Sim', 'Não']]),
            'data.campo_teste'
        );

        $this->assertInstanceOf(Radio::class, $component);
        $this->assertSame(['Sim' => 'Sim', 'Não' => 'Não'], $component->getOptions());
    }

    public function test_checkbox_gera_toggle(): void
    {
        $component = $this->factory->make($this->field(FieldTypeEnum::CHECKBOX), 'data.campo_teste');

        $this->assertInstanceOf(Toggle::class, $component);
    }

    public function test_email_gera_text_input_de_email(): void
    {
        $component = $this->factory->make($this->field(FieldTypeEnum::EMAIL), 'data.campo_teste');

        $this->assertInstanceOf(TextInput::class, $component);
        $this->assertTrue($component->isEmail());
    }

    public function test_state_path_e_label_vem_do_campo(): void
    {
        foreach (FieldTypeEnum::cases() as $type) {
            $component = $this->factory->make($this->field($type), 'custom_data.campo_teste');

            $this->assertSame('custom_data.campo_teste', $component->getName(), $type->value);
            $this->assertSame('Campo de teste', $component->getLabel(), $type->value);
        }
    }

    public function test_placeholder_e_default_do_campo_sao_aplicados(): void
    {
        $component = $this->factory->make(
            $this->field(FieldTypeEnum::TEXT, ['placeholder' => 'Digite aqui', 'default_value' => 'padrão']),
            'data.campo_teste'
        );

        $this->assertSame('Digite aqui', $component->getPlaceholder());
        $this->assertSame('padrão', $component->getDefaultState());
    }

    public function test_sem_placeholder_e_default_nada_e_aplicado(): void
    {
        $component = $this->factory->make($this->field(FieldTypeEnum::TEXT), 'data.campo_teste');

        $this->assertNull($component->getPlaceholder());
        $this->assertNull($component->getDefaultState());
    }

    public function test_obrigatoriedade_nao_e_decidida_pela_fabrica(): void
    {
        $component = $this->factory->make(
            $this->field(FieldTypeEnum::TEXT, ['is_required' => true]),
            'data.campo_teste'
        );

        $this->assertFalse($component->isRequired());
    }
}
