<?php

namespace App\Services;

use App\Enums\FieldTypeEnum;
use App\Enums\UserRoleEnum;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\CustomRecord;
use App\Models\CustomRecordType;
use App\Models\Macroprocess;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Fonte de dados do Relatório de Cadastros: esconde a diferença entre os dois
 * jeitos de guardar um cadastro.
 *
 * - Cadastro criado pelo admin: linhas de `custom_records`, valores em `data_json`.
 * - Cadastro base (is_system): tabela própria (clientes, projetos...), com colunas
 *   fixas e os campos extras em `custom_data`.
 *
 * Só monta consulta e filtros. Quem pode ver campo de valor continua sendo
 * decidido por MoneyFieldVisibility, em quem chama.
 */
class CadastroReportSource
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $columnsCache = null;

    /** @var array<string, array<string, string>> */
    private array $optionsCache = [];

    private function __construct(public readonly CustomRecordType $type) {}

    public static function for(CustomRecordType $type): self
    {
        return new self($type);
    }

    /**
     * Cadastros que o relatório oferece: os criados pelo admin e os base conhecidos.
     */
    public static function availableTypes(): Collection
    {
        return CustomRecordType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (CustomRecordType $t) => ! $t->is_system || isset(self::definitions()[$t->model_class]));
    }

    public function isSystem(): bool
    {
        return (bool) $this->type->is_system;
    }

    /** Coluna JSON onde ficam os campos do cadastro. */
    public function jsonColumn(): string
    {
        return $this->isSystem() ? 'custom_data' : 'data_json';
    }

    /**
     * Colunas fixas de cada cadastro base. Tipos (`kind`):
     * text, bool, date, options (valores de um conjunto) e relation (outro cadastro).
     * Ficam de fora valores monetários e avaliações (orçamento, gasto, nota do
     * patrocinador): não passam pela regra de visibilidade de valores.
     *
     * @return array<class-string, array<string, array<string, mixed>>>
     */
    private static function definitions(): array
    {
        // Montado uma vez: o relatório consulta isso por célula
        static $definitions = null;

        return $definitions ??= self::buildDefinitions();
    }

    /**
     * @return array<class-string, array<string, array<string, mixed>>>
     */
    private static function buildDefinitions(): array
    {
        $text = fn (string $label) => ['label' => $label, 'kind' => 'text'];
        $date = fn (string $label) => ['label' => $label, 'kind' => 'date'];
        $bool = fn (string $label) => ['label' => $label, 'kind' => 'bool'];
        $relation = fn (string $label, string $relation, string $fk, string $model) => [
            'label' => $label, 'kind' => 'relation', 'relation' => $relation, 'fk' => $fk, 'model' => $model,
        ];

        return [
            Client::class => [
                'code' => $text('Número'), 'name' => $text('Nome'), 'email' => $text('E-mail'),
                'phone' => $text('Telefone'), 'is_active' => $bool('Ativo'),
            ],
            Project::class => [
                'number' => $text('Número'), 'acronym' => $text('Sigla'), 'name' => $text('Nome'),
                'client_id' => $relation('Cliente', 'client', 'client_id', Client::class),
                'entity_id' => $relation('Processo', 'entity', 'entity_id', CustomEntity::class),
                'description' => $text('Descrição'),
                'start_date_forecast' => $date('Início previsto'), 'end_date_forecast' => $date('Fim previsto'),
                'end_date' => $date('Fim real'),
                'contact_name' => $text('Contato'), 'manager_name' => $text('Gerente'),
                'sponsor_name' => $text('Patrocinador'), 'scrum_master_name' => $text('Scrum Master'),
                'product_owner_name' => $text('Product Owner'), 'is_active' => $bool('Ativo'),
            ],
            Supplier::class => [
                'code' => $text('Número'), 'name' => $text('Nome'), 'email' => $text('E-mail'),
                'phone' => $text('Telefone'), 'business' => $text('Ramo de atividade'),
                'description' => $text('Descrição'),
            ],
            User::class => [
                'code' => $text('Número'), 'name' => $text('Nome'), 'email' => $text('E-mail'),
                'phone' => $text('Telefone'),
                'user_role' => ['label' => 'Perfil', 'kind' => 'options', 'options' => fn () => UserRoleEnum::options()],
                'is_active' => $bool('Ativo'),
            ],
            Macroprocess::class => [
                'code' => $text('Número'), 'name' => $text('Nome'), 'acronym' => $text('Sigla'),
                'description' => $text('Descrição'),
                // Lê o que está gravado, para cobrir também valores antigos e as mudanças da cadeia de valor
                'value_chain_function' => ['label' => 'Função na Cadeia de Valor', 'kind' => 'options', 'options' => fn () => Macroprocess::query()
                    ->whereNotNull('value_chain_function')->distinct()->orderBy('value_chain_function')
                    ->pluck('value_chain_function', 'value_chain_function')->all()],
                'manager_id' => $relation('Gestor', 'manager', 'manager_id', User::class),
                'is_active' => $bool('Ativo'),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fixedColumns(): array
    {
        return $this->columnsCache ??= $this->isSystem()
            ? (self::definitions()[$this->type->model_class] ?? [])
            : [
                'code' => ['label' => 'Número', 'kind' => 'text'],
                'created_by' => ['label' => 'Criado por', 'kind' => 'relation', 'relation' => 'creator', 'fk' => 'created_by', 'model' => User::class],
                'created_at' => ['label' => 'Criado em', 'kind' => 'date'],
            ];
    }

    /**
     * Opções de uma coluna `options` ou `relation`: [valor => rótulo].
     *
     * @return array<string, string>
     */
    public function fixedOptions(string $key): array
    {
        return $this->optionsCache[$key] ??= $this->loadFixedOptions($key, $this->fixedColumns()[$key]);
    }

    /**
     * @param  array<string, mixed>  $column
     * @return array<string, string>
     */
    private function loadFixedOptions(string $key, array $column): array
    {
        if ($column['kind'] === 'options') {
            return ($column['options'])();
        }

        // Só quem aparece neste cadastro: no Macroprocesso, "Gestor" lista quem gerencia algum
        // macroprocesso, e não todos os usuários
        $usedIds = $this->query()->whereNotNull($key)->distinct()->pluck($key);

        return ($column['model'])::query()->whereKey($usedIds)->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Texto de exibição de uma coluna fixa (tela e CSV).
     */
    public function fixedValue(object $record, string $key): string
    {
        $column = $this->fixedColumns()[$key];
        $value = $record->{$key};
        $value = $value instanceof \BackedEnum ? $value->value : $value;

        return match ($column['kind']) {
            'bool' => $value ? 'Sim' : 'Não',
            'date' => $value ? $value->format($key === 'created_at' ? 'd/m/Y H:i' : 'd/m/Y') : '—',
            'relation' => $record->{$column['relation']}?->name ?? '—',
            'options' => filled($value) ? ($this->fixedOptions($key)[$value] ?? (string) $value) : '—',
            default => filled($value) ? (string) $value : '—',
        };
    }

    /**
     * Filtro de uma coluna fixa. `$data`: `value` (texto), `values` (lista),
     * `from`/`until` (período).
     */
    public function applyFixedFilter(Builder $query, string $key, array $data): Builder
    {
        return match ($this->fixedColumns()[$key]['kind']) {
            // `%` e `_` digitados são literais (ESCAPE '!'), não curingas
            'text' => $query->when(filled($data['value'] ?? null), fn ($q) => $q->whereRaw(
                $q->getQuery()->getGrammar()->wrap($q->qualifyColumn($key))." LIKE ? ESCAPE '!'",
                [$this->likeTerm($data['value'])],
            )),
            'options', 'relation' => $query->when(! empty($data['values'] ?? null), fn ($q) => $q->whereIn($key, $data['values'])),
            'date' => $query
                ->when(filled($data['from'] ?? null), fn ($q) => $q->whereDate($key, '>=', $data['from']))
                ->when(filled($data['until'] ?? null), fn ($q) => $q->whereDate($key, '<=', $data['until'])),
            default => $query,
        };
    }

    public function query(): Builder
    {
        $query = $this->isSystem()
            ? ($this->type->model_class)::query()
            : CustomRecord::query()->where('custom_record_type_id', $this->type->id);

        // Evita uma consulta por linha para o nome exibido das colunas de relação
        $relations = collect($this->fixedColumns())->where('kind', 'relation')->pluck('relation')->values()->all();

        return $query->with($relations);
    }

    /**
     * @return Collection<int, CustomField>
     */
    public function fields(): Collection
    {
        return $this->type->fields;
    }

    /**
     * Valor guardado do campo no registro, como o relatório o lê.
     */
    public function rawValue(object $record, CustomField $field): mixed
    {
        return ($record->{$this->jsonColumn()} ?? [])[$field->key] ?? null;
    }

    /**
     * Aplica o filtro de um campo conforme o tipo. `$data` é o estado do filtro
     * (ver CadastroReports::fieldFilter): `value`, `from`/`until` ou `min`/`max`.
     */
    public function applyFieldFilter(Builder $query, CustomField $field, array $data): Builder
    {
        $path = $this->jsonColumn().'->'.$field->key;

        return match ($field->field_type) {
            // No MySQL o JSON é comparado em utf8mb4_bin (diferencia caixa), então LOWER nos dois lados
            FieldTypeEnum::TEXT, FieldTypeEnum::TEXTAREA, FieldTypeEnum::EMAIL => $query->when(
                filled($data['value'] ?? null),
                fn ($q) => $q->whereNotNull($path)->whereRaw(
                    'LOWER('.$q->getQuery()->getGrammar()->wrap($path).") LIKE ? ESCAPE '!'",
                    [$this->likeTerm($data['value'], lower: true)],
                ),
            ),
            FieldTypeEnum::SELECT, FieldTypeEnum::RADIO, FieldTypeEnum::COMBO => $query->when(
                filled($data['value'] ?? null),
                fn ($q) => $q->where($path, $data['value']),
            ),
            FieldTypeEnum::CHECKBOX => $query->when(
                in_array($data['value'] ?? null, ['1', '0'], true),
                fn ($q) => $data['value'] === '1'
                    ? $q->where($path, true)
                    : $q->where(fn ($w) => $w->where($path, false)->orWhereNull($path)),
            ),
            // whereNotNull: no MySQL um JSON null viraria o texto 'null' e entraria no período
            FieldTypeEnum::DATE => $query
                ->when(filled($data['from'] ?? null) || filled($data['until'] ?? null), fn ($q) => $q->whereNotNull($path))
                ->when(filled($data['from'] ?? null), fn ($q) => $q->where($path, '>=', $data['from']))
                ->when(filled($data['until'] ?? null), fn ($q) => $q->where($path, '<=', $data['until'])),
            FieldTypeEnum::NUMBER, FieldTypeEnum::CURRENCY => $this->applyNumberRange($query, $path, $data),
            default => $query,
        };
    }

    /**
     * Faixa numérica sobre JSON. O valor pode ter sido gravado como texto ("800")
     * ou número; sem o CAST o SQLite compara texto com número e erra. A expressão
     * do campo vem da gramática do banco, então vale no MySQL e no SQLite.
     */
    private function applyNumberRange(Builder $query, string $path, array $data): Builder
    {
        $expression = 'CAST('.$query->getQuery()->getGrammar()->wrap($path).' AS DECIMAL(20,4))';

        // whereNotNull: o CAST de um JSON null daria 0 no MySQL e entraria na faixa
        return $query
            ->when(is_numeric($data['min'] ?? null) || is_numeric($data['max'] ?? null), fn ($q) => $q->whereNotNull($path))
            ->when(is_numeric($data['min'] ?? null), fn ($q) => $q->whereRaw("{$expression} >= ?", [$data['min']]))
            ->when(is_numeric($data['max'] ?? null), fn ($q) => $q->whereRaw("{$expression} <= ?", [$data['max']]));
    }

    /**
     * Termo de LIKE "contém" com `!` como escape: `%`, `_` e `!` digitados valem como texto.
     */
    private function likeTerm(string $value, bool $lower = false): string
    {
        $value = $lower ? mb_strtolower($value) : $value;

        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }
}
