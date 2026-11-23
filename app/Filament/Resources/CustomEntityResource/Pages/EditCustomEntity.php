<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomEntityResource;
use App\Filament\Resources\CustomEntityResource\Pages\Concerns\HasVersioningHeaderActions;
use App\Models\CustomEntity;
use App\Services\ProcessVersioningService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditCustomEntity extends EditRecord
{
    use HasVersioningHeaderActions;

    protected static string $resource = CustomEntityResource::class;

    protected static string $view = 'filament.resources.custom-entity-resource.pages.edit-custom-entity';

    /**
     * Versão antiga é somente leitura: vai para a visualização em vez de abrir o formulário
     */
    public function mount(int|string $record): void
    {
        $entity = CustomEntity::find($record);

        if ($entity && ! $entity->is_current && ! $entity->is_draft) {
            Notification::make()
                ->title(ProcessVersioningService::INACTIVE_VERSION_MESSAGE)
                ->warning()
                ->send();

            $this->record = $entity;
            $this->redirect(CustomEntityResource::getUrl('view', ['record' => $entity]));
            $this->skipRender();

            return;
        }

        parent::mount($record);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->display_code ? "Editar {$this->record->display_code} — {$this->record->name}" : parent::getTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->publishVersionAction(),
            $this->goToDraftAction(),
            $this->editStructureAction(),
            $this->inactiveVersionsAction(),

            Actions\Action::make('field_permissions')
                ->label('Permissões de Campos')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->url(fn () => CustomEntityResource::getUrl('permissions', ['record' => $this->record]))
                ->visible(fn () => auth()->user()?->user_role === UserRoleEnum::ADMIN),

            Actions\DeleteAction::make()
                ->before(fn (Actions\DeleteAction $action) => CustomEntityResource::haltIfDeletionBlocked($action, [$this->record])),
        ];
    }
}
