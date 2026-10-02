<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransitionRule extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'status_transition_id',
        'rule_order',
        'conditions',
        'to_status_id',
    ];

    protected $casts = [
        'conditions' => 'array',
        'rule_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // Gateways encadeados não são suportados: uma regra nunca pode
        // apontar para a própria situação Condicional
        static::saving(function (TransitionRule $rule) {
            $isConditional = ProcessStatus::whereKey($rule->to_status_id)
                ->where('system_key', 'conditional')
                ->exists();

            if ($isConditional) {
                throw ValidationException::withMessages([
                    'to_status_id' => 'Uma regra não pode apontar para a situação Condicional (gateways encadeados não são suportados).',
                ]);
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

    public function statusTransition(): BelongsTo
    {
        return $this->belongsTo(StatusTransition::class);
    }

    /**
     * Situação de destino quando a regra casa ("Então vai para")
     */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(ProcessStatus::class, 'to_status_id');
    }
}
