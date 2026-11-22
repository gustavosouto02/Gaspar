<?php

namespace App\Exceptions;

/**
 * Exceção de domínio para bloqueios na criação de nova versão de processo.
 * A mensagem é sempre amigável e pronta para exibição ao usuário.
 */
class ProcessVersioningException extends \RuntimeException {}
