<?php

namespace App\Concerns;

use App\Enums\FieldTypeEnum;
use App\Models\CustomRecordType;
use Filament\Forms;

trait HasCustomFields
{
    /**
     * Initialize the trait — merge custom_data cast.
     */
    public function initializeHasCustomFields(): void
    {
        $this->mergeFillable(['custom_data']);
        $this->mergeCasts(['custom_data' => 'array']);
    }

    /**
     * Get the CustomRecordType entry for this model.
     */
    public function getCustomRecordType(): ?CustomRecordType
    {
        return CustomRecordType::where('model_class', static::class)
            ->where('is_system', true)
            ->first();
    }

    /**
     * Get the custom fields associated with this model's type.
     */
    public function getCustomFields()
    {
        $type = $this->getCustomRecordType();
        if (! $type) {
            return collect();
        }

        return $type->fields;
    }

    /**
     * Dummy relationship for the CustomFieldsRelationManager.
     * The actual query is overridden in the RelationManager.
     */
    public function customFieldsViaType(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        // This will never be used directly — CustomFieldsRelationManager overrides getTableQuery()
        return $this->hasMany(\App\Models\CustomField::class, 'id', 'id')->whereRaw('1 = 0');
    }

    /**
     * Build Filament form components for custom fields.
     * Returns a Section component with all custom fields, or null if none exist.
     */
    public static function buildCustomFieldComponents(): ?Forms\Components\Section
    {
        $type = CustomRecordType::where('model_class', static::class)
            ->where('is_system', true)
            ->with('fields')
            ->first();

        if (! $type || $type->fields->isEmpty()) {
            return null;
        }

        $components = [];

        foreach ($type->fields as $field) {
            $key = "custom_data.{$field->key}";

            $component = match ($field->field_type) {
                FieldTypeEnum::TEXT     => Forms\Components\TextInput::make($key),
                FieldTypeEnum::TEXTAREA => Forms\Components\Textarea::make($key)->rows(3),
                FieldTypeEnum::NUMBER   => Forms\Components\TextInput::make($key)->numeric(),
                FieldTypeEnum::DATE     => Forms\Components\DatePicker::make($key),
                FieldTypeEnum::SELECT   => Forms\Components\Select::make($key)
                    ->options($field->options_json
                        ? array_combine($field->options_json, $field->options_json)
                        : []),
                FieldTypeEnum::RADIO    => Forms\Components\Radio::make($key)
                    ->options($field->options_json
                        ? array_combine($field->options_json, $field->options_json)
                        : []),
                FieldTypeEnum::CHECKBOX => Forms\Components\Toggle::make($key),
                FieldTypeEnum::EMAIL    => Forms\Components\TextInput::make($key)->email(),
                default                 => Forms\Components\TextInput::make($key),
            };

            $component->label($field->name)
                      ->required($field->is_required);

            if (method_exists($component, 'placeholder') && $field->placeholder) {
                $component->placeholder($field->placeholder);
            }

            if ($field->default_value) {
                $component->default($field->default_value);
            }

            $components[] = $component;
        }

        return Forms\Components\Section::make('Campos Customizados')
            ->schema($components)
            ->columns(2)
            ->collapsible()
            ->collapsed(false);
    }
}
