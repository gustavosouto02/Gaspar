<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Filament\Resources\CustomEntityResource;
use App\Models\CustomEntity;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Versões antigas (não vigentes) de um processo, somente leitura. O processo vem em
 * `?processo=<id da raiz>`, obrigatório: não há listagem das versões antigas de todo o sistema.
 */
class ListInactiveCustomEntityVersions extends ListRecords
{
    protected static string $resource = CustomEntityResource::class;

    #[Url(as: 'processo')]
    public ?string $processo = null;

    public function mount(): void
    {
        parent::mount();

        // O Filament só confere o acesso depois do mount(): sem isto quem não é ADMIN veria 404 em vez de 403
        abort_unless(CustomEntityResource::canAccess(), 403);
        abort_unless($this->processo && CustomEntity::withTrashed()->whereKey($this->processo)->exists(), 404);
    }

    public function getTitle(): string|Htmlable
    {
        $root = CustomEntity::withTrashed()->find($this->processo);

        return $root ? "Versões inativas — {$root->name}" : 'Versões inativas';
    }

    public function getBreadcrumb(): string
    {
        return 'Versões inativas';
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->inactiveVersions()
            ->where('root_entity_id', $this->processo)
            ->with(['rootEntity', 'macroprocess', 'manager', 'creator']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Voltar para Processos')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => CustomEntityResource::getUrl('index')),
        ];
    }
}
