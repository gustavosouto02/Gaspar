<?php

namespace App\Console\Commands;

use App\Enums\FieldTypeEnum;
use App\Models\CustomField;
use App\Models\CustomRecord;
use App\Models\CustomRecordType;
use App\Models\DemandFieldValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditLegacyCurrencyValues extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:audit-currency-values';

    /**
     * @var string
     */
    protected $description = 'Lista valores de campos de moeda no formato antigo x.xxx (ex.: "1.500"), que o novo padrão lê como milhar (1.500,00), para conferência. Não altera dados.';

    /** Casa "1.500" e "1.234.567", o formato ambíguo (antes era lido como decimal) */
    private const AMBIGUOUS = '/^-?\d{1,3}(\.\d{3})+$/';

    public function handle(): int
    {
        $found = [];

        // Campos do processo (demand_field_values)
        $currencyFieldIds = CustomField::where('field_type', FieldTypeEnum::CURRENCY->value)->pluck('name', 'id');

        DemandFieldValue::whereIn('custom_field_id', $currencyFieldIds->keys())
            ->cursor()
            ->each(function (DemandFieldValue $fv) use (&$found, $currencyFieldIds) {
                if (preg_match(self::AMBIGUOUS, trim((string) $fv->value))) {
                    $found[] = ['demand_field_values', $fv->id, $currencyFieldIds[$fv->custom_field_id], $fv->value];
                }
            });

        // Cadastros dinâmicos (custom_records.data_json) e base (custom_data na própria tabela)
        foreach (CustomRecordType::with('fields')->get() as $type) {
            $keys = $type->fields
                ->where('field_type', FieldTypeEnum::CURRENCY)
                ->pluck('name', 'key');

            if ($keys->isEmpty()) {
                continue;
            }

            if ($type->is_system && $type->model_class && class_exists($type->model_class)) {
                $table = (new $type->model_class)->getTable();
                $column = 'custom_data';
                $rows = DB::table($table)->whereNotNull($column)->get(['id', $column]);
            } else {
                $table = (new CustomRecord)->getTable();
                $column = 'data_json';
                $rows = DB::table($table)->where('custom_record_type_id', $type->id)->get(['id', $column]);
            }

            foreach ($rows as $row) {
                $data = json_decode((string) $row->{$column}, true);

                foreach ($keys as $key => $name) {
                    if (is_array($data) && isset($data[$key]) && is_scalar($data[$key])
                        && preg_match(self::AMBIGUOUS, trim((string) $data[$key]))) {
                        $found[] = ["{$table}.{$column}", $row->id, $name, $data[$key]];
                    }
                }
            }
        }

        if ($found === []) {
            $this->info('Nenhum valor de moeda no formato ambíguo x.xxx encontrado.');

            return self::SUCCESS;
        }

        $this->warn(count($found).' valor(es) de moeda no formato x.xxx: confira se são milhares (1.500 = R$ 1.500,00) ou decimais antigos.');
        $this->table(['Origem', 'Registro', 'Campo', 'Valor gravado'], $found);

        return self::SUCCESS;
    }
}
