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

            // Sanitiza conditions para evitar que {min: null, max: null} contamine operadores que não são range
            if (is_array($rule->conditions)) {
                $conditions = $rule->conditions;
                foreach ($conditions as &$cond) {
                    $op = isset($cond['operator']) ? \App\Enums\RuleOperatorEnum::tryFrom($cond['operator']) : null;
                    if ($op && $op->valueKind() !== 'range' && is_array($cond['value'] ?? null)) {
                        $val = $cond['value'];
                        if (array_key_exists('min', $val) && array_key_exists('max', $val) && $val['min'] === null && $val['max'] === null) {
                            $cond['value'] = null;
                        }
                    }
                }
                $rule->conditions = $conditions;
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
