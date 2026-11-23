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
    /**
     * Memo por requisição: isReadOnly() é chamado a cada ação/linha da tabela, e cada
     * chamada faria um exists em demands. Propriedade protected não é hidratada pelo
     * Livewire, então o cache nasce vazio em toda requisição (nunca fica velho entre elas). Nenhuma
     * ação dos relation managers altera demandas nem a vigência do dono, então não há o que invalidar
     * dentro da requisição.
     */
    protected ?bool $structureLocked = null;

    protected bool $structureNoticeResolved = false;

    protected ?string $structureNotice = null;

    public function isReadOnly(): bool
    {
        return parent::isReadOnly()
            || ($this->structureLocked ??= app(ProcessVersioningService::class)->isStructureLocked($this->getOwnerRecord()));
    }

    /** Aviso para a descrição da tabela (null quando a estrutura está liberada) */
    protected function structureLockedNotice(): ?string
    {
        if (! $this->structureNoticeResolved) {
            $this->structureNotice = app(ProcessVersioningService::class)->lockedMessage($this->getOwnerRecord());
            $this->structureNoticeResolved = true;
        }

        return $this->structureNotice;
    }
}
