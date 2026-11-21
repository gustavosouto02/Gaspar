<?php

namespace App\Models;

use App\Concerns\HasSequentialCode;
use App\Enums\FieldTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CustomField extends Model
{
    use HasFactory, HasSequentialCode, HasUuids, SoftDeletes;

    protected string $sequentialCodePrefix = 'CMP';

    protected string $sequentialCodeColumn = 'code';

    protected $fillable = [
        'code',
        'name',
        'key',
        'field_type',
        'placeholder',
        'is_required',
        'default_value',
        'options_json',
        'created_by',
    ];

    protected static function booted(): void
    {
        // Exclusão definitiva apagaria os valores gravados (cascade); campo com
        // valores só pode ser excluído "de mentira" (soft delete)
        static::forceDeleting(function (CustomField $field) {
            if ($field->hasStoredValues()) {
                throw new \RuntimeException('Campo com valores gravados não pode ser excluído definitivamente.');
            }
        });
    }

    /**
     * Gerar UUID7 para a chave primária
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * Casts dos atributos
     */
    protected function casts(): array
    {
        return [
            'field_type' => FieldTypeEnum::class,
            'is_required' => 'boolean',
            'options_json' => 'array',
            'field_order' => 'integer',
        ];
    }

    private ?bool $storedValuesCache = null;

    /**
     * O campo já tem valor gravado em alguma demanda, registro de cadastro
     * ou cadastro base? Nesse caso tipo e origem não podem mais mudar.
     */
    public function hasStoredValues(): bool
    {
        if (! $this->exists) {
            return false;
        }

        return $this->storedValuesCache ??= $this->detectStoredValues();
    }

    private function detectStoredValues(): bool
    {
        $inDemands = DemandFieldValue::where('custom_field_id', $this->id)
            ->whereNotNull('value')->where('value', '!=', '')->exists();

        if ($inDemands) {
            return true;
        }

        foreach ($this->customRecordTypes()->get() as $type) {
            $query = $type->is_system && $type->model_class
                ? $type->model_class::withoutGlobalScopes()
                    ->whereNotNull("custom_data->{$this->key}")
                    ->where("custom_data->{$this->key}", '!=', '')
                : CustomRecord::withTrashed()
                    ->where('custom_record_type_id', $type->id)
                    ->whereNotNull("data_json->{$this->key}")
                    ->where("data_json->{$this->key}", '!=', '');

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Relacionamento com CustomEntities
     */
    public function customEntities(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(CustomEntity::class, 'custom_entity_custom_field')
                    ->using(CustomEntityCustomFieldPivot::class)
                    ->withPivot('field_order')
                    ->withTimestamps();
    }

    /**
     * Relacionamento com Tipos de Registros Customizados
     */
    public function customRecordTypes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(CustomRecordType::class, 'custom_record_type_custom_field', 'custom_field_id', 'custom_record_type_id')
                    ->withPivot('field_order')
                    ->withTimestamps();
    }

    /**
     * Relacionamento com o criador
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
