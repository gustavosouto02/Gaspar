<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomEntityResource;
use App\Filament\Resources\CustomEntityResource\Pages\Concerns\HasVersioningHeaderActions;
use App\Filament\Resources\MacroprocessResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewCustomEntity extends ViewRecord
{
    use HasVersioningHeaderActions;

    protected static string $resource = CustomEntityResource::class;

    protected static string $view = 'filament.resources.custom-entity-resource.pages.view-custom-entity';

    /**
     * Única página do resource aberta a quem não é ADMIN: o canAccess() do resource continua
     * restrito (menu, busca global e demais páginas). Qual versão pode ser vista é a policy "view".
     */
    public static function authorizeResourceAccess(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /**
     * Quem não é ADMIN não acessa a listagem de processos: a trilha volta ao macroprocesso
     */
    public function getBreadcrumbs(): array
    {
        if (auth()->user()?->user_role === UserRoleEnum::ADMIN) {
            return parent::getBreadcrumbs();
        }

        $macroprocess = $this->record->macroprocess;

        return [
            ...($macroprocess ? [MacroprocessResource::getUrl('view', ['record' => $macroprocess]) => $macroprocess->full_display_name] : []),
            $this->getBreadcrumb(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->display_code ? "{$this->record->display_code} — {$this->record->name}" : $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->publishVersionAction(),
            $this->goToDraftAction(),
            $this->editStructureAction(),
            $this->inactiveVersionsAction(),
            Actions\EditAction::make(),
        ];
    }
}
