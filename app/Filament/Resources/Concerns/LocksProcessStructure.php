<?php

namespace App\Filament\Resources\Concerns;

use App\Services\ProcessVersioningService;

/**
 * Para os relation managers da estrutura do processo (campos, situações,
 * transições, papéis): somente leitura quando a versão tem demandas ou não é a vigente.
 * Com isReadOnly() verdadeiro o Filament esconde (e recusa no servidor) vincular,
 * desvincular, criar, editar e excluir.
 */
trait LocksProcessStructure
{
    public function isReadOnly(): bool
    {
        return parent::isReadOnly()
            || app(ProcessVersioningService::class)->isStructureLocked($this->getOwnerRecord());
    }

    /** Aviso para a descrição da tabela (null quando a estrutura está liberada) */
    protected function structureLockedNotice(): ?string
    {
        return app(ProcessVersioningService::class)->lockedMessage($this->getOwnerRecord());
    }
}
