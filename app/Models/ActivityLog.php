<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Número sequencial amigável do registro auditado (com fallback para o ID) */
    public function getAuditableNumberAttribute(): string
    {
        $fromPayload = $this->new_values['code']
            ?? $this->old_values['code']
            ?? $this->new_values['number']
            ?? $this->old_values['number']
            ?? null;

        if ($fromPayload) {
            return (string) $fromPayload;
        }

        if ($this->auditable_type && $this->auditable_id && class_exists($this->auditable_type)) {
            $query = $this->auditable_type::query();
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($this->auditable_type), true)) {
                $query->withTrashed();
            }
            $record = $query->find($this->auditable_id);
            if ($record) {
                return (string) ($record->code ?? $record->number ?? $this->auditable_id);
            }
        }

        return (string) $this->auditable_id;
    }

    /** Label amigável para exibição do tipo auditado */
    public function getAuditableTypeLabelAttribute(): string
    {
        return class_basename($this->auditable_type);
    }

    /** Label amigável do evento */
    public function getEventLabelAttribute(): string
    {
        return match ($this->event) {
            'created' => 'Criado',
            'updated' => 'Atualizado',
            'deleted' => 'Excluído',
            default   => ucfirst($this->event),
        };
    }

    public function getEventColorAttribute(): string
    {
        return match ($this->event) {
            'created' => 'success',
            'updated' => 'info',
            'deleted' => 'danger',
            default   => 'gray',
        };
    }
}
