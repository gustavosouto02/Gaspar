<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Filament\Resources\CustomEntityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomEntity extends ViewRecord
{
    protected static string $resource = CustomEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('field_permissions')
                ->label('Permissões de Campos')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->url(fn () => CustomEntityResource::getUrl('permissions', ['record' => $this->record]))
                ->visible(fn () => auth()->user()?->user_role === \App\Enums\UserRoleEnum::ADMIN),
            Actions\EditAction::make(),
        ];
    }
}
