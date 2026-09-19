<?php

namespace App\Services;

use App\Models\TransitionRule;

/**
 * Resultado da avaliação da tabela de decisão de uma transição condicional.
 */
final readonly class TransitionDecision
{
    public function __construct(
        public string $toStatusId,
        public ?TransitionRule $matchedRule, // null = caiu no "senão" (default_to_status_id)
    ) {
    }
}
