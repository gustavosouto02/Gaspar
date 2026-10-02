<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StatusTransition extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'entity_id',
        'from_status_id',
        'to_status_id',
        'default_to_status_id',
        'label',
        'allow_return',
        'allowed_role_ids',
        'display_order',
    ];

    protected $casts = [
        'allowed_role_ids' => 'array',
    ];

    protected static function booted(): void
    {
        // Gateways encadeados não são suportados: o "senão" nunca pode
        // apontar para a própria situação Condicional
        static::saving(function (StatusTransition $transition) {
            if (! $transition->default_to_status_id) {
                return;
            }

            $isConditional = ProcessStatus::whereKey($transition->default_to_status_id)
                ->where('system_key', 'conditional')
                ->exists();

            if ($isConditional) {
                throw ValidationException::withMessages([
                    'default_to_status_id' => 'O "senão" não pode apontar para a situação Condicional (gateways encadeados não são suportados).',
                ]);
            }
        });
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /** Processo ao qual a transição pertence */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(CustomEntity::class, 'entity_id');
    }

    /** Situação de origem (null = qualquer) */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(ProcessStatus::class, 'from_status_id');
    }

    /** Situação de destino */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(ProcessStatus::class, 'to_status_id');
    }

    /** Situação "senão" da tabela de decisão (quando o destino é a Condicional) */
    public function defaultToStatus(): BelongsTo
    {
        return $this->belongsTo(ProcessStatus::class, 'default_to_status_id');
    }

    /** Regras da tabela de decisão, na ordem de avaliação (hit policy FIRST) */
    public function rules(): HasMany
    {
        return $this->hasMany(TransitionRule::class)->orderBy('rule_order');
    }

    /**
     * Verifica se um usuário pode disparar esta transição em uma demanda específica.
     * ADMIN sempre pode. Se allowed_role_ids estiver vazio, qualquer membro/envolvido pode.
     */
    public function canBeTriggeredBy(User $user, Demand $demand): bool
    {
        if ($user->user_role?->value === 'ADMIN') {
            return true;
        }

        if ($demand->parent_demand_id) {
            $parent = $demand->parent;
            if ($parent && $parent->assigned_to === $user->id) {
                return true; // Responsável pela demanda mãe pode tudo na subdemanda
            }
        }

        $allowedRoles = $this->allowed_role_ids ?? [];

        // Sem restrição: qualquer membro do processo ou envolvido na demanda pode
        if (empty($allowedRoles)) {
            if ($demand->requested_by === $user->id) return true;
            if ($demand->assigned_to === $user->id) return true;

            return ProcessMember::where('entity_id', $demand->entity_id)
                ->where('user_id', $user->id)
                ->exists();
        }

        // Verifica papel dinâmico: Demandante
        if (in_array('__requester__', $allowedRoles) && $demand->requested_by === $user->id) {
            return true;
        }

        // Verifica papel dinâmico: Responsável
        if (in_array('__assignee__', $allowedRoles) && $demand->assigned_to === $user->id) {
            return true;
        }

        // Verifica se o usuário tem algum dos papéis estáticos no processo
        return ProcessMember::where('entity_id', $demand->entity_id)
            ->where('user_id', $user->id)
            ->whereIn('process_role_id', $allowedRoles)
            ->exists();
    }
}
