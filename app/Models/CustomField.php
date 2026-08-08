<?php

namespace App\Models;

use App\Enums\FieldTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CustomField extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'key',
        'field_type',
        'placeholder',
        'is_required',
        'default_value',
        'options_json',
        'created_by',
    ];

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
