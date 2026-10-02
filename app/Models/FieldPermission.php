<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use App\Enums\FieldPermissionEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FieldPermission extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'entity_id',
        'custom_field_id',
        'process_status_id',
        'process_role_id',
        'permission',
    ];

    protected $casts = [
        'permission' => FieldPermissionEnum::class,
    ];

    /**
     * Gerar UUID7 para a chave primária
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(CustomEntity::class, 'entity_id');
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    public function processStatus(): BelongsTo
    {
        return $this->belongsTo(ProcessStatus::class, 'process_status_id');
    }

    /** Papel do processo (null = regra genérica, todos os papéis) */
    public function processRole(): BelongsTo
    {
        return $this->belongsTo(ProcessRole::class, 'process_role_id');
    }
}
