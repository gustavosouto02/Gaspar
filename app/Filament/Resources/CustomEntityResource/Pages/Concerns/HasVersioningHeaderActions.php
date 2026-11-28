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
     * "Criar nova versão": só aparece quando a versão vigente já tem demandas (a estrutura
     * está travada). Sem demandas a estrutura é editada direto, sem criar versão.
     */
    protected function editStructureAction(): Actions\Action
    {
        return Actions\Action::make('edit_structure')
            ->label('Criar nova versão')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn () => auth()->user()?->user_role === UserRoleEnum::ADMIN
                && $this->record->is_current
                && app(ProcessVersioningService::class)->hasDemands($this->record)
                && ! $this->draftRecord())
            ->requiresConfirmation()
            ->modalHeading('Criar nova versão do processo')
            ->modalDescription(fn () => 'Esta versão já tem demandas, por isso a estrutura (campos, situações, transições, permissões e papéis) não pode ser alterada. Será criado um rascunho da versão v'.app(ProcessVersioningService::class)->nextVersionNumber($this->record).', copiado desta. Esta versão continua vigente, recebendo demandas, até você publicar o rascunho; ao publicar, ela passa a ser antiga (intacta, só não abre novas demandas) e as demandas em aberto seguem nela.')
            ->modalSubmitActionLabel('Criar rascunho')
            ->action(function () {
                try {
                    $newVersion = app(ProcessVersioningService::class)->createNewVersion($this->record);
                } catch (ProcessVersioningException $e) {
                    Notification::make()->title('Não foi possível criar a nova versão')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("Rascunho {$newVersion->version_label} criado")
                    ->body('Edite o que precisar e use "Publicar" para colocá-lo em produção.')
                    ->success()
                    ->send();

                $this->redirect(CustomEntityResource::getUrl('edit', ['record' => $newVersion]));
            });
    }

    /** Rascunho em aberto do processo desta página (não se confunde com a própria página) */
    protected function draftRecord(): ?CustomEntity
    {
        return app(ProcessVersioningService::class)->draftFor($this->record->root_entity_id ?: $this->record->id);
    }

    /** Na versão vigente: atalho para o rascunho em andamento */
    protected function goToDraftAction(): Actions\Action
    {
        return Actions\Action::make('go_to_draft')
            ->label('Ir para o rascunho')
            ->icon('heroicon-o-document-duplicate')
            ->color('warning')
            ->visible(fn () => auth()->user()?->user_role === UserRoleEnum::ADMIN
                && $this->record->is_current
                && $this->draftRecord() !== null)
            ->url(fn () => CustomEntityResource::getUrl('edit', ['record' => $this->draftRecord()]));
    }

    /** "Publicar": só no rascunho; ele assume o lugar da versão vigente */
    protected function publishVersionAction(): Actions\Action
    {
        return Actions\Action::make('publish_version')
            ->label('Publicar')
            ->icon('heroicon-o-rocket-launch')
            ->color('success')
            ->visible(fn () => auth()->user()?->user_role === UserRoleEnum::ADMIN && $this->record->is_draft)
            ->requiresConfirmation()
            ->modalHeading(fn () => "Publicar {$this->record->version_label}")
            ->modalDescription('A versão atual passa a ser antiga: fica intacta e só deixa de aceitar novas demandas (as em aberto continuam nela). Este rascunho vira a versão vigente e passa a receber novas demandas. Depois de publicada, a estrutura não pode mais ser alterada livremente.')
            ->modalSubmitActionLabel('Publicar')
            ->action(function () {
                try {
                    $published = app(ProcessVersioningService::class)->publishVersion($this->record);
                } catch (ProcessVersioningException $e) {
                    Notification::make()->title('Não foi possível publicar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("Versão {$published->version_label} publicada")
                    ->body('Ela agora é a versão vigente do processo.')
                    ->success()
                    ->send();

                $this->redirect(CustomEntityResource::getUrl('view', ['record' => $published]));
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
