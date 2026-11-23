<?php

namespace App\Filament\Resources\CustomEntityResource\Pages\Concerns;

use App\Enums\UserRoleEnum;
use App\Exceptions\ProcessVersioningException;
use App\Filament\Resources\CustomEntityResource;
use App\Models\CustomEntity;
use App\Services\ProcessVersioningService;
use Filament\Actions;
use Filament\Notifications\Notification;

/**
 * Ações de versionamento do processo, para as páginas de visualização e edição.
 *
 * @property CustomEntity $record
 */
trait HasVersioningHeaderActions
{
    /**
     * "Editar estrutura": só aparece quando a versão vigente já tem demandas (a estrutura
     * está travada). Sem demandas a estrutura é editada direto, sem criar versão.
     */
    protected function editStructureAction(): Actions\Action
    {
        return Actions\Action::make('edit_structure')
            ->label('Editar estrutura')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn () => auth()->user()?->user_role === UserRoleEnum::ADMIN
                && $this->record->is_current
                && app(ProcessVersioningService::class)->hasDemands($this->record))
            ->requiresConfirmation()
            ->modalHeading('Editar estrutura do processo')
            ->modalDescription(fn () => 'Esta versão já tem demandas, por isso a estrutura (campos, situações, transições, permissões e papéis) não pode ser alterada. Será criada a versão v'.app(ProcessVersioningService::class)->nextVersionNumber($this->record).', copiada desta. As demandas existentes continuam nesta versão, que passa a ser antiga; só a nova versão recebe novas demandas.')
            ->modalSubmitActionLabel('Criar nova versão')
            ->action(function () {
                try {
                    $newVersion = app(ProcessVersioningService::class)->createNewVersion($this->record);
                } catch (ProcessVersioningException $e) {
                    Notification::make()->title('Não foi possível criar a nova versão')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("Nova versão {$newVersion->version_label} criada")
                    ->body('Agora você pode editar a estrutura da nova versão.')
                    ->success()
                    ->send();

                $this->redirect(CustomEntityResource::getUrl('edit', ['record' => $newVersion]));
            });
    }

    /** Link para as versões antigas deste processo (só quando existem) */
    protected function inactiveVersionsAction(): Actions\Action
    {
        return Actions\Action::make('inactive_versions')
            ->label('Versões inativas')
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->visible(fn () => auth()->user()?->user_role === UserRoleEnum::ADMIN
                && CustomEntity::inactiveVersions()->where('root_entity_id', $this->record->root_entity_id)->exists())
            ->url(fn () => CustomEntityResource::getUrl('inactive-versions', ['processo' => $this->record->root_entity_id]));
    }
}
