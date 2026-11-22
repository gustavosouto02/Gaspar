<?php

namespace App\Services;

use App\Enums\FieldTypeEnum;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomRecord;
use App\Models\CustomRecordType;
use App\Models\Macroprocess;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Origens possíveis do campo "Vínculo com cadastro" (FieldTypeEnum::COMBO).
 *
 * A configuração do campo fica em options_json:
 * { "source": "client|project|supplier|user|macroprocess|process|record_type",
 *   "record_type_id": "<uuid, só quando source = record_type>" }
 *
 * O valor guardado é o UUID do registro. Registro apagado devolve null em
 * label(); quem exibe decide o texto (REMOVED).
 */
class LookupResolver
{
    public const REMOVED = '(removido)';

    public const RECORD_TYPE = 'record_type';

    private const LIMIT = 50;

    /** @var array<string, string|null> chave do campo-rótulo por cadastro */
    private array $labelKeys = [];

    /** @var array<string, string|null> rótulo já resolvido por origem + id (null = removido) */
    private array $labels = [];

    /**
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        return [
            'client' => 'Clientes',
            'project' => 'Projetos',
            'supplier' => 'Fornecedores',
            'user' => 'Usuários',
            'macroprocess' => 'Macroprocessos',
            'process' => 'Processos',
            self::RECORD_TYPE => 'Outros Cadastros',
        ];
    }

    /**
     * Resultados da busca paginada: [uuid => rótulo].
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    public function search(array $config, string $term): array
    {
        $query = $this->query($config);
        if (! $query) {
            return [];
        }

        $term = trim($term);
        $source = $config['source'] ?? null;

        if ($source === self::RECORD_TYPE) {
            return $this->searchRecords($query, $term, (string) $config['record_type_id']);
        }

        if ($term !== '') {
            $query->where(function (Builder $q) use ($source, $term) {
                $q->where('name', 'like', "%{$term}%");
                if (in_array($source, ['project', 'macroprocess'], true)) {
                    $q->orWhere('acronym', 'like', "%{$term}%");
                }
            });
        }

        $with = $source === 'process' ? ['macroprocess'] : [];

        if ($source === 'process') {
            // Só processos ativos, como no formulário da demanda
            $query->where('is_active', true);
        }

        return $query->with($with)->orderBy('name')->limit(self::LIMIT)->get()
            ->mapWithKeys(fn ($model) => [$model->getKey() => $this->modelLabel($source, $model)])
            ->all();
    }

    /**
     * Rótulo do registro ou null quando ele não existe mais.
     *
     * @param  array<string, mixed>  $config
     */
    public function label(array $config, mixed $id): ?string
    {
        if (blank($id) || ! is_string($id)) {
            return null;
        }

        // Uma célula por registro repete o mesmo vínculo: guarda o rótulo (inclusive "removido")
        $cacheKey = ($config['source'] ?? '').'|'.($config['record_type_id'] ?? '').'|'.$id;
        if (array_key_exists($cacheKey, $this->labels)) {
            return $this->labels[$cacheKey];
        }

        $query = $this->query($config, forLabel: true);
        if (! $query) {
            return null;
        }

        $model = $query->whereKey($id)->first();

        return $this->labels[$cacheKey] = $model ? $this->modelLabel($config['source'], $model) : null;
    }

    private function query(array $config, bool $forLabel = false): ?Builder
    {
        $query = match ($config['source'] ?? null) {
            'client' => Client::query(),
            'project' => Project::query(),
            'supplier' => Supplier::query(),
            // Na busca só entram usuários ativos; o rótulo de quem já estava
            // vinculado continua aparecendo se o usuário for desativado.
            'user' => $forLabel ? User::query() : User::query()->where('is_active', true),
            'macroprocess' => Macroprocess::query(),
            'process' => CustomEntity::query(),
            self::RECORD_TYPE => filled($config['record_type_id'] ?? null)
                ? CustomRecord::query()->where('custom_record_type_id', $config['record_type_id'])
                : null,
            default => null,
        };

        return $query;
    }

    private function modelLabel(string $source, mixed $model): string
    {
        return match ($source) {
            'project' => $model->display_name,
            'macroprocess', 'process' => $model->full_display_name,
            self::RECORD_TYPE => $this->recordLabel($model),
            default => (string) $model->name,
        };
    }

    /**
     * Código + valor do primeiro campo (que não seja de valor) do cadastro.
     */
    private function recordLabel(CustomRecord $record): string
    {
        $key = $this->labelKey($record->custom_record_type_id);
        $value = $key ? ($record->data_json[$key] ?? null) : null;

        return is_scalar($value) && filled($value)
            ? "{$record->code} - {$value}"
            : (string) $record->code;
    }

    /**
     * Chave do campo usado no rótulo, descoberta uma vez por cadastro.
     */
    private function labelKey(string $recordTypeId): ?string
    {
        if (! array_key_exists($recordTypeId, $this->labelKeys)) {
            $type = CustomRecordType::with('fields')->find($recordTypeId);
            $this->labelKeys[$recordTypeId] = $type?->fields->first(
                fn ($f) => $f->field_type !== FieldTypeEnum::CURRENCY
                    && $f->field_type !== FieldTypeEnum::COMBO
            )?->key;
        }

        return $this->labelKeys[$recordTypeId];
    }

    /**
     * A busca é feita no banco (código e campo-rótulo), então não depende
     * do total de registros do cadastro.
     */
    private function searchRecords(Builder $query, string $term, string $recordTypeId): array
    {
        if ($term !== '') {
            $key = $this->labelKey($recordTypeId);

            $query->where(function (Builder $q) use ($term, $key) {
                $q->where('code', 'like', "%{$term}%");
                if ($key) {
                    // Case-insensitive: no MySQL o JSON é comparado em utf8mb4_bin.
                    $column = $q->getQuery()->getGrammar()->wrap("data_json->{$key}");
                    $q->orWhereRaw("LOWER({$column}) LIKE ?", ['%'.mb_strtolower($term).'%']);
                }
            });
        }

        return $query->orderBy('code')->limit(self::LIMIT)->get()
            ->mapWithKeys(fn (CustomRecord $r) => [$r->getKey() => $this->recordLabel($r)])
            ->all();
    }
}
