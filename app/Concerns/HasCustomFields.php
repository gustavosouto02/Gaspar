<?php

namespace App\Concerns;

use App\Models\CustomRecordType;
use App\Services\CustomFieldComponentFactory;
use App\Services\MoneyFieldVisibility;
use Filament\Forms;

trait HasCustomFields
{
    /**
     * Boot da trait: campos de valor de quem não pode vê-los nunca são regravados
     * (o formulário os omite, e o JSON de custom_data é salvo por inteiro).
     */
    public static function bootHasCustomFields(): void
    {
        static::saving(function ($model) {
            if (! auth()->check() || ! $model->isDirty('custom_data')) {
                return;
            }

            $type = $model->getCustomRecordType();
            if (! $type) {
                return;
            }

            $model->custom_data = app(MoneyFieldVisibility::class)->preserveHiddenValues(
                $type,
                (array) $model->custom_data,
                (array) $model->getOriginal('custom_data'),
                $type->fields
            );
        });
    }

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

        $moneyVisibility = app(MoneyFieldVisibility::class);
        $fieldFactory = app(CustomFieldComponentFactory::class);

        foreach ($type->fields as $field) {
            // Campos de valor só aparecem para ADMIN e para os gestores do cadastro
            if ($moneyVisibility->isHiddenFor(auth()->user(), $field, $type)) {
                continue;
            }

            $component = $fieldFactory->make($field, "custom_data.{$field->key}")
                ->required($field->is_required);

            $components[] = $component;
        }

        return Forms\Components\Section::make('Campos Customizados')
            ->schema($components)
            ->columns(2)
            ->collapsible()
            ->collapsed(false);
    }
}
