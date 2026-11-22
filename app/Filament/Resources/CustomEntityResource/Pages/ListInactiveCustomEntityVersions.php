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
 * Versões antigas (não vigentes) de processos, somente leitura. Com `?processo=<id da raiz>`
 * lista só as versões antigas daquele processo.
 */
class ListInactiveCustomEntityVersions extends ListRecords
{
    protected static string $resource = CustomEntityResource::class;

    #[Url(as: 'processo')]
    public ?string $processo = null;

    public function getTitle(): string|Htmlable
    {
        $root = $this->processo ? CustomEntity::withTrashed()->find($this->processo) : null;

        return $root ? "Versões inativas — {$root->name}" : 'Versões inativas de processos';
    }

    public function getBreadcrumb(): string
    {
        return 'Versões inativas';
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->inactiveVersions()
            ->when($this->processo, fn (Builder $query) => $query->where('root_entity_id', $this->processo))
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
