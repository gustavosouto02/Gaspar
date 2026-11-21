<?php

namespace App\Services;

use App\Enums\FieldTypeEnum;
use App\Models\CustomField;
use App\Support\MoneyInput;

/**
 * Converte o valor guardado de um campo customizado em texto de exibição:
 * vínculo (UUID) → rótulo do registro, moeda → "R$ …", lista JSON → texto.
 *
 * Só formata. Quem pode ver o valor (MoneyFieldVisibility) continua sendo
 * decidido por quem chama.
 */
class CustomFieldValueFormatter
{
    public function format(CustomField $field, mixed $value, string $empty = '—'): string
    {
        if (blank($value)) {
            return $empty;
        }

        if ($field->field_type === FieldTypeEnum::COMBO) {
            return app(LookupResolver::class)->label($field->options_json ?? [], $value)
                ?? LookupResolver::REMOVED;
        }

        if ($field->field_type === FieldTypeEnum::CURRENCY) {
            return MoneyInput::display($value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return implode(', ', $decoded);
            }
        }

        return is_scalar($value) ? (string) $value : $empty;
    }
}
