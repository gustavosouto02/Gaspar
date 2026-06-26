<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CustomEntity extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'is_active',
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
     * Casts de atributos
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relacionamento com o usuário criador
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relacionamento com os campos dinâmicos da entidade
     */
    public function fields(): HasMany
    {
        return $this->hasMany(CustomField::class, 'entity_id')->orderBy('field_order');
    }

    /**
     * Membros vinculados ao processo
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProcessMember::class, 'entity_id');
    }

    /**
     * Situações vinculadas ao processo
     */
    public function processStatuses(): BelongsToMany
    {
        return $this->belongsToMany(ProcessStatus::class, 'entity_process_status', 'entity_id', 'process_status_id')
            ->withPivot('display_order')
            ->orderByPivot('display_order')
            ->withTimestamps();
    }

    /**
     * Transições de situação configuradas para este processo
     */
    public function statusTransitions(): HasMany
    {
        return $this->hasMany(StatusTransition::class, 'entity_id')
            ->orderBy('display_order');
    }
}
