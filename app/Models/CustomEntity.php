<?php

namespace App\Models;

use App\Concerns\HasSequentialCode;
use App\Concerns\LogsActivity;
use App\Exceptions\ProcessDeletionBlockedException;
use App\Services\ProcessVersioningService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomEntity extends Model
{
    use HasFactory, HasSequentialCode, HasUuids, LogsActivity, SoftDeletes;

    protected string $sequentialCodePrefix = 'PRC';

    protected string $sequentialCodeColumn = 'code';

    protected static function booted()
    {
        parent::booted();

        // Todo processo nasce como a versão 1 vigente de si mesmo; o clone de
        // versão já chega com a raiz preenchida pelo ProcessVersioningService
        static::creating(function (CustomEntity $entity) {
            if (empty($entity->root_entity_id)) {
                $entity->root_entity_id = $entity->id;
            }
        });

        // Regra do PO: a versão só pode ser apagada quando todas as demandas
        // dela estiverem encerradas. Vale para qualquer caminho (tela, tinker, job)
        static::deleting(function (CustomEntity $entity) {
            if (app(ProcessVersioningService::class)->hasPendingDemands($entity)) {
                throw new ProcessDeletionBlockedException(ProcessVersioningService::DELETION_BLOCKED_MESSAGE);
            }
        });

        // Apagada a versão vigente, o processo não pode ficar sem versão para receber
        // demandas: relê do banco (não confia no is_current em memória) e, se não restou
        // nenhuma vigente, promove a versão não excluída de maior número da mesma raiz
        static::deleted(function (CustomEntity $entity) {
            $rootId = $entity->root_entity_id ?: $entity->id;

            DB::transaction(function () use ($rootId) {
                // Rascunho nunca é promovido por exclusão: só o "Publicar" o torna vigente
                $versions = static::query()->where('root_entity_id', $rootId)->lockForUpdate()->get();

                if ($versions->isEmpty() || $versions->contains('is_current', true)) {
                    return;
                }

                $candidate = $versions->where('is_draft', false)->sortByDesc('version')->first();

                $candidate?->forceFill(['is_current' => true])->saveQuietly();
            });
        });

        static::created(function (CustomEntity $entity) {
            $systemStatuses = ProcessStatus::where('is_system', true)->get();
            $order = 1;
            foreach ($systemStatuses as $status) {
                // "Nova" goes first (order 1), others go later (order 99, 100)
                $displayOrder = $status->system_key === 'new' ? 1 : 99 + $order++;
                $entity->processStatuses()->attach($status->id, ['display_order' => $displayOrder]);
            }
        });
    }

    /**
     * Versão, raiz, vigência e versão anterior não são preenchíveis em massa:
     * quem define é o ProcessVersioningService.
     */
    protected $attributes = [
        'version' => 1,
        'is_current' => true,
        'is_draft' => false,
    ];

    protected $fillable = [
        'code',
        'name',
        'macroprocess_id',
        'description',
        'is_active',
        'purpose',
        'sla_hours',
        'created_by',
        'manager_id',
    ];

    public function macroprocess(): BelongsTo
    {
        return $this->belongsTo(Macroprocess::class);
    }

    /**
     * Nome com a sigla do macroprocesso, sem a versão
     */
    public function getBaseDisplayNameAttribute(): string
    {
        $acronym = $this->macroprocess?->acronym;

        return $acronym ? "{$acronym} - {$this->name}" : $this->name;
    }

    /**
     * Nome com a sigla do macroprocesso; inclui a versão quando ela é maior que 1
     */
    public function getFullDisplayNameAttribute(): string
    {
        $name = $this->base_display_name;

        return ($this->version ?? 1) > 1 ? "{$name} ({$this->version_label})" : $name;
    }

    /**
     * Rótulo da versão: "v1", "v2"...
     */
    public function getVersionLabelAttribute(): string
    {
        return 'v'.($this->version ?? 1);
    }

    /**
     * Código exibido na interface: o da raiz (versão 1) + a versão, ex.: PRC-2026-0007 · v2.
     * O clone tem um código interno próprio (índice único), que não aparece para o usuário.
     */
    public function getDisplayCodeAttribute(): ?string
    {
        $rootCode = $this->root_entity_id && $this->root_entity_id !== $this->id
            ? ($this->rootEntity?->code ?? $this->code)
            : $this->code;

        return $rootCode ? "{$rootCode} · {$this->version_label}" : null;
    }

    /**
     * Opções para filtros: todas as versões; quando o processo tem mais de uma,
     * cada uma recebe o rótulo "v1 (anterior)" / "v2 (vigente)".
     *
     * @return array<string, string>
     */
    public static function versionedFilterOptions(): array
    {
        // Rascunho ainda não tem demandas: fica fora dos filtros
        $entities = static::with('macroprocess')->where('is_draft', false)->get();
        $versionsPerRoot = $entities->countBy(fn (CustomEntity $e) => $e->root_entity_id ?? $e->id);

        return $entities
            ->sortBy(fn (CustomEntity $e) => [$e->base_display_name, $e->version])
            ->mapWithKeys(function (CustomEntity $e) use ($versionsPerRoot) {
                if ($versionsPerRoot->get($e->root_entity_id ?? $e->id, 1) < 2) {
                    return [$e->id => $e->base_display_name];
                }

                $state = $e->is_current ? 'vigente' : 'anterior';

                return [$e->id => "{$e->base_display_name} {$e->version_label} ({$state})"];
            })
            ->all();
    }

    /** Apenas as versões vigentes */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_current'), true);
    }

    /** Apenas as versões antigas (não vigentes e que já foram publicadas) */
    public function scopeInactiveVersions(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_current'), false)
            ->where($query->qualifyColumn('is_draft'), false);
    }

    /** Apenas os rascunhos (versões em elaboração) */
    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_draft'), true);
    }

    /** Versão vigente ou rascunho: o que aparece na lista principal de processos */
    public function scopeCurrentOrDraft(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where($q->qualifyColumn('is_current'), true)
            ->orWhere($q->qualifyColumn('is_draft'), true));
    }

    /** Versão 1 do processo (a raiz do histórico de versões) */
    public function rootEntity(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_entity_id')->withTrashed();
    }

    /** Todas as versões do mesmo processo (inclui esta) */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'root_entity_id', 'root_entity_id')->orderBy('version');
    }

    /** Versão da qual esta foi clonada */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id')->withTrashed();
    }

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
            'is_current' => 'boolean',
            'is_draft' => 'boolean',
            'version' => 'integer',
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
     * Gestor responsável pelo processo
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Relacionamento com os campos dinâmicos da entidade
     */
    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(CustomField::class, 'custom_entity_custom_field')
            ->using(CustomEntityCustomFieldPivot::class)
            ->withPivot('field_order')
            ->orderByPivot('field_order')
            ->withTimestamps();
    }

    /**
     * Situação em que a demanda nasce: a "Nova" ou, sem ela, a primeira do processo
     */
    public function initialProcessStatus(): ?ProcessStatus
    {
        return $this->processStatuses()->where('system_key', 'new')->first()
            ?? $this->processStatuses()->orderBy('display_order')->first();
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
