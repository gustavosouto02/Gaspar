<?php

namespace App\Livewire;

use App\Enums\UserRoleEnum;
use App\Models\CustomEntity;
use App\Services\ProcessKanbanBoard;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Seção "Quadro de demandas" da página do processo (somente leitura, só ADMIN).
 */
class ProcessDemandsKanban extends Component
{
    #[Locked]
    public string $entityId;

    public function mount(string $entityId): void
    {
        abort_unless(auth()->user()?->user_role === UserRoleEnum::ADMIN, 403);

        $this->entityId = $entityId;
    }

    public function render(ProcessKanbanBoard $board): View
    {
        abort_unless(auth()->user()?->user_role === UserRoleEnum::ADMIN, 403);

        $entity = CustomEntity::findOrFail($this->entityId);

        return view('livewire.process-demands-kanban', [
            'entity' => $entity,
            'columns' => $board->columns($entity),
            'limit' => ProcessKanbanBoard::CARDS_PER_COLUMN,
        ]);
    }
}
