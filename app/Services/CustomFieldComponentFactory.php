<?php

namespace App\Services;

use App\Enums\FieldTypeEnum;
use App\Models\CustomField;
use App\Support\MoneyInput;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * Monta o componente de formulário de um campo customizado a partir do tipo.
 *
 * Concentra o que era repetido na demanda, nos registros de cadastro e nos
 * cadastros base: componente por tipo, label, placeholder e valor padrão.
 *
 * Fica de fora, por ser decisão de cada tela: visibilidade (MoneyFieldVisibility),
 * obrigatoriedade e permissão (FieldPermissionResolver na demanda, is_required
 * nos cadastros).
 */
class CustomFieldComponentFactory
{
    public function make(CustomField $field, string $statePath): Component
    {
        $component = match ($field->field_type) {
            FieldTypeEnum::TEXT => TextInput::make($statePath),
            FieldTypeEnum::TEXTAREA => Textarea::make($statePath)->rows(3),
            FieldTypeEnum::NUMBER => TextInput::make($statePath)->numeric(),
            FieldTypeEnum::CURRENCY => MoneyInput::make($statePath),
            FieldTypeEnum::DATE => DatePicker::make($statePath),
            FieldTypeEnum::SELECT => Select::make($statePath)
                ->options($this->options($field)),
            FieldTypeEnum::RADIO => Radio::make($statePath)
                ->options($this->options($field)),
            FieldTypeEnum::CHECKBOX => Toggle::make($statePath),
            FieldTypeEnum::EMAIL => TextInput::make($statePath)->email(),
            default => TextInput::make($statePath),
        };

        $component->label($field->name);

        if (method_exists($component, 'placeholder') && $field->placeholder) {
            $component->placeholder($field->placeholder);
        }

        if ($field->default_value) {
            $component->default($field->default_value);
        }

        return $component;
    }

    /**
     * @return array<string, string>
     */
    private function options(CustomField $field): array
    {
        return $field->options_json
            ? array_combine($field->options_json, $field->options_json)
            : [];
    }
}
