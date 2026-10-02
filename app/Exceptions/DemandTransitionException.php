<?php

namespace App\Exceptions;

/**
 * Exceção de domínio para bloqueios de transição de demanda.
 * A mensagem é sempre amigável e pronta para exibição ao usuário.
 */
class DemandTransitionException extends \RuntimeException
{
}
