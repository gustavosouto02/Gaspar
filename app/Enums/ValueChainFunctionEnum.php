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

    /** Classe da área do bloco no desenho do painel (grid-area). */
    public function area(): string
    {
        return match ($this) {
            self::DEFINIR_DIRETRIZES => 'vc-area-diretrizes',
            self::ENTREGAR => 'vc-area-entregar',
            self::RELACIONAMENTO => 'vc-area-relacionamento',
            self::SUPORTAR => 'vc-area-suportar',
        };
    }

    /** Se os macroprocessos do bloco ficam empilhados (coluna) em vez de lado a lado. */
    public function isStacked(): bool
    {
        return $this === self::DEFINIR_DIRETRIZES;
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
