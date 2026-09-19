<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ProcessStatus extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'name',
        'color',
        'is_system',
        'system_key',
        'created_by',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Gerar UUID7 para a chave primária
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * Helpers de situação de sistema (comparação por system_key, nunca por nome)
     */
    public function isNew(): bool
    {
        return $this->system_key === 'new';
    }

    public function isClosed(): bool
    {
        return $this->system_key === 'closed';
    }

    public function isEvaluated(): bool
    {
        return $this->system_key === 'evaluated';
    }

    public function isCanceled(): bool
    {
        return $this->system_key === 'canceled';
    }

    public function isConditional(): bool
    {
        return $this->system_key === 'conditional';
    }

    public function scopeSystemKey($query, string $key)
    {
        return $query->where('system_key', $key);
    }

    /**
     * Exclui a situação "Condicional" (gateway) de listagens e selects
     */
    public function scopeWithoutConditional($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('process_statuses.system_key')
                ->orWhere('process_statuses.system_key', '!=', 'conditional');
        });
    }

    public static function findBySystemKey(string $key): ?self
    {
        return static::query()->systemKey($key)->first();
    }

    /**
     * Relacionamento com o usuário criador
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Processos que usam esta situação
     */
    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(CustomEntity::class, 'entity_process_status', 'process_status_id', 'entity_id')
            ->withPivot('display_order')
            ->withTimestamps();
    }
}
