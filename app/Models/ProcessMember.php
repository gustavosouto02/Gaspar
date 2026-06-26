<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProcessMember extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'entity_id',
        'user_id',
        'process_role_id',
    ];

    /**
     * Gerar UUID7 para a chave primária
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * Processo ao qual o membro pertence
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(CustomEntity::class, 'entity_id');
    }

    /**
     * Usuário membro
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Papel do membro neste processo
     */
    public function processRole(): BelongsTo
    {
        return $this->belongsTo(ProcessRole::class, 'process_role_id');
    }
}
