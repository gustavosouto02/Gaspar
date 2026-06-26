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
