<?php

namespace App\Exceptions;

/**
 * Exceção de domínio para a trava de exclusão de processo (versão) com demandas pendentes.
 * A mensagem é sempre amigável e pronta para exibição ao usuário.
 */
class ProcessDeletionBlockedException extends \RuntimeException {}
