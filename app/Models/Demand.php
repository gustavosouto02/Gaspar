<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Demand extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'entity_id',
        'process_status_id',
        'client_id',
        'project_id',
        'requested_by',
        'assigned_to',
        'created_by',
        'parent_demand_id',
        'title',
        'description',
        'status',
        'priority',
        'sla_due_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status'       => DemandStatusEnum::class,
        'priority'     => DemandPriorityEnum::class,
        'sla_due_at'   => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /** Processo (CustomEntity) */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(CustomEntity::class, 'entity_id');
    }

    /** Situação atual */
    public function processStatus(): BelongsTo
    {
        return $this->belongsTo(ProcessStatus::class, 'process_status_id');
    }

    /** Cliente */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /** Projeto */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** Demandante */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Responsável */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Criador */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Demanda mãe (para subdemandas) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Demand::class, 'parent_demand_id');
    }

    /** Subdemandas */
    public function children(): HasMany
    {
        return $this->hasMany(Demand::class, 'parent_demand_id');
    }

    /** Relatos de tratamento */
    public function comments(): HasMany
    {
        return $this->hasMany(DemandComment::class)->latest();
    }

    /** Valores dos campos customizados do processo */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(DemandFieldValue::class);
    }

    /** Verifica se a demanda pode ser concluída (sem subdemandas abertas) */
    public function canBeCompleted(): bool
    {
        return ! $this->children()
            ->where('status', DemandStatusEnum::ACTIVE->value)
            ->exists();
    }

    /**
     * Auto-atribui o responsável (assigned_to) com base na situação atual
     * e nos papéis permitidos para as próximas transições.
     */
    public function autoAssign(): void
    {
        if (! $this->process_status_id) {
            $this->updateQuietly(['assigned_to' => null]);
            return;
        }

        $transitions = StatusTransition::where('entity_id', $this->entity_id)
            ->where(function ($q) {
                $q->whereNull('from_status_id')
                  ->orWhere('from_status_id', $this->process_status_id);
            })
            ->get();

        $updates = [];

        // Se não houver próximas transições, a demanda chegou ao fim do fluxo
        if ($transitions->isEmpty()) {
            $updates['assigned_to'] = null;
            if ($this->status !== DemandStatusEnum::COMPLETED) {
                $updates['status']       = DemandStatusEnum::COMPLETED;
                $updates['completed_at'] = now();
            }
            $this->updateQuietly($updates);
            return;
        } else {
            // Se houver transições e estava concluída, reabre
            if ($this->status === DemandStatusEnum::COMPLETED) {
                $updates['status']       = DemandStatusEnum::ACTIVE;
                $updates['completed_at'] = null;
            }
        }

        $roleIds        = [];
        $allowRequester = false;

        foreach ($transitions as $transition) {
            $roles = $transition->allowed_role_ids ?? [];
            if (empty($roles)) {
                continue;
            }
            if (in_array('__requester__', $roles)) {
                $allowRequester = true;
            }
            $roleIds = array_merge($roleIds, array_diff($roles, ['__requester__', '__assignee__']));
        }

        $roleIds = array_unique($roleIds);

        // Se só o demandante pode atuar, volta para ele
        if ($allowRequester && empty($roleIds)) {
            if ($this->requested_by !== $this->assigned_to) {
                $updates['assigned_to'] = $this->requested_by;
            }
            if (! empty($updates)) $this->updateQuietly($updates);
            return;
        }

        // Busca membros do processo que tenham um dos papéis estáticos exigidos
        if (! empty($roleIds)) {
            $member = ProcessMember::where('entity_id', $this->entity_id)
                ->whereIn('process_role_id', $roleIds)
                ->first();

            if ($member && $member->user_id !== $this->assigned_to) {
                $updates['assigned_to'] = $member->user_id;
            } elseif (! $member && $this->assigned_to) {
                $updates['assigned_to'] = null;
            }
        }

        if (! empty($updates)) {
            $this->updateQuietly($updates);
        }
    }

    /**
     * Verifica se o usuário logado tem permissão para disparar alguma 
     * transição a partir da situação atual da demanda.
     */
    public function canBeTransitionedBy(User $user): bool
    {
        if ($user->user_role?->value === 'ADMIN') {
            return true;
        }

        $transitions = StatusTransition::where('entity_id', $this->entity_id)
            ->where(function ($q) {
                $q->whereNull('from_status_id')
                  ->orWhere('from_status_id', $this->process_status_id);
            })
            ->get();

        foreach ($transitions as $transition) {
            if ($transition->canBeTriggeredBy($user, $this)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna uma string descrevendo quem são os responsáveis atuais 
     * com base nas transições de status da etapa atual.
     */
    public function getCurrentResponsiblesAttribute(): string
    {
        if (! $this->process_status_id) {
            return '—';
        }

        $transitions = StatusTransition::where('entity_id', $this->entity_id)
            ->where(function ($q) {
                $q->whereNull('from_status_id')
                  ->orWhere('from_status_id', $this->process_status_id);
            })
            ->get();

        if ($transitions->isEmpty()) {
            return 'Sem próximas ações configuradas';
        }

        $labels = collect();
        $staticRoleIds = [];

        foreach ($transitions as $transition) {
            $roles = $transition->allowed_role_ids ?? [];
            if (empty($roles)) {
                return 'Qualquer membro do processo';
            }
            if (in_array('__requester__', $roles)) {
                $labels->push('Demandante (' . ($this->requester?->name ?? '?') . ')');
            }
            if (in_array('__assignee__', $roles)) {
                $labels->push('Responsável atual (' . ($this->assignee?->name ?? '?') . ')');
            }
            $staticRoleIds = array_merge($staticRoleIds, array_diff($roles, ['__requester__', '__assignee__']));
        }

        $staticRoleIds = array_unique($staticRoleIds);

        if (! empty($staticRoleIds)) {
            // Busca os papéis exigidos
            $roles = ProcessRole::whereIn('id', $staticRoleIds)->get();
            foreach ($roles as $role) {
                // Tenta achar quem são as pessoas com esse papel no processo
                $members = ProcessMember::with('user')
                    ->where('entity_id', $this->entity_id)
                    ->where('process_role_id', $role->id)
                    ->get();
                
                if ($members->isEmpty()) {
                    $labels->push("{$role->name} (Sem membros atribuídos)");
                } else {
                    $names = $members->map(fn ($m) => $m->user?->name)->filter()->implode(', ');
                    $labels->push("{$role->name} ({$names})");
                }
            }
        }

        return $labels->unique()->implode(' / ');
    }

    /**
     * Escopo para filtrar demandas que estão pendentes para um usuário
     * (Demandante, Responsável atual, ou papéis dinâmicos nas transições de status).
     */
    public function scopePendingForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $userId = $user->id;
            $q->where('requested_by', $userId)
              ->orWhere('assigned_to', $userId)
              ->orWhereExists(function ($query) use ($userId) {
                  $query->select(\Illuminate\Support\Facades\DB::raw(1))
                      ->from('status_transitions')
                      ->whereColumn('status_transitions.entity_id', 'demands.entity_id')
                      ->where(function ($q2) {
                          $q2->whereColumn('status_transitions.from_status_id', 'demands.process_status_id')
                             ->orWhereNull('status_transitions.from_status_id');
                      })
                      ->where(function ($q3) use ($userId) {
                          $q3->whereNull('status_transitions.allowed_role_ids')
                             ->orWhereRaw("JSON_LENGTH(status_transitions.allowed_role_ids) = 0")
                             ->orWhereRaw("JSON_CONTAINS(status_transitions.allowed_role_ids, '\"__requester__\"') AND demands.requested_by = ?", [$userId])
                             ->orWhereRaw("JSON_CONTAINS(status_transitions.allowed_role_ids, '\"__assignee__\"') AND demands.assigned_to = ?", [$userId])
                             ->orWhereExists(function ($q4) use ($userId) {
                                 $q4->select(\Illuminate\Support\Facades\DB::raw(1))
                                     ->from('process_members')
                                     ->whereColumn('process_members.entity_id', 'status_transitions.entity_id')
                                     ->where('process_members.user_id', $userId)
                                     ->whereRaw('JSON_CONTAINS(status_transitions.allowed_role_ids, JSON_QUOTE(process_members.process_role_id))');
                             });
                      });
              });
        });
    }
}
