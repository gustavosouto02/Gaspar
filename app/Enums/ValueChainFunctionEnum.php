<?php

namespace App\Enums;

/**
 * Função do macroprocesso na cadeia de valor. Os valores são os textos já
 * gravados em `macroprocesses.value_chain_function`; a ordem define o desenho no painel.
 */
enum ValueChainFunctionEnum: string
{
    case DEFINIR_DIRETRIZES = 'Definir Diretrizes e Estratégia';
    case ENTREGAR = 'Entregar Produtos e Serviços';
    case RELACIONAMENTO = 'Manter Relacionamentos';
    case SUPORTAR = 'Suportar Processos';

    public function label(): string
    {
        return $this->value;
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
