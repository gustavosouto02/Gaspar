<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Filament\Resources\CustomEntityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomEntity extends EditRecord
{
    protected static string $resource = CustomEntityResource::class;

    protected static string $view = 'filament.resources.custom-entity-resource.pages.edit-custom-entity';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('field_permissions')
                ->label('Permissões de Campos')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->url(fn () => CustomEntityResource::getUrl('permissions', ['record' => $this->record]))
                ->visible(fn () => auth()->user()?->user_role === \App\Enums\UserRoleEnum::ADMIN),

            Actions\DeleteAction::make(),
        ];
    }
}
