{{-- Cópia de vendor/filament/filament/resources/views/resources/pages/view-record.blade.php (Filament v3.3.50).
     Alterações: título "Sistematização do processo:" acima dos relation managers e seção "Quadro de demandas" (Livewire, só ADMIN) ao final.
     Ao atualizar o Filament, comparar com o original. --}}
<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @php
        $relationManagers = $this->getRelationManagers();
        $hasCombinedRelationManagerTabsWithContent = $this->hasCombinedRelationManagerTabsWithContent();
    @endphp

    @if ((! $hasCombinedRelationManagerTabsWithContent) || (! count($relationManagers)))
        @if ($this->hasInfolist())
            {{ $this->infolist }}
        @else
            <div
                wire:key="{{ $this->getId() }}.forms.{{ $this->getFormStatePath() }}"
            >
                {{ $this->form }}
            </div>
        @endif
    @endif

    @if (count($relationManagers))
        <div class="flex flex-col gap-y-4">
            @include('filament.resources.custom-entity-resource.partials.systematization-heading')

            <x-filament-panels::resources.relation-managers
                :active-locale="isset($activeLocale) ? $activeLocale : null"
                :active-manager="$this->activeRelationManager ?? ($hasCombinedRelationManagerTabsWithContent ? null : array_key_first($relationManagers))"
                :content-tab-label="$this->getContentTabLabel()"
                :content-tab-icon="$this->getContentTabIcon()"
                :content-tab-position="$this->getContentTabPosition()"
                :managers="$relationManagers"
                :owner-record="$record"
                :page-class="static::class"
            >
                @if ($hasCombinedRelationManagerTabsWithContent)
                    <x-slot name="content">
                        @if ($this->hasInfolist())
                            {{ $this->infolist }}
                        @else
                            {{ $this->form }}
                        @endif
                    </x-slot>
                @endif
            </x-filament-panels::resources.relation-managers>
        </div>
    @endif

    @if (auth()->user()?->user_role === \App\Enums\UserRoleEnum::ADMIN)
        @livewire(\App\Livewire\ProcessDemandsKanban::class, ['entityId' => $record->getKey()], key('process-demands-kanban-'.$record->getKey()))
    @endif
</x-filament-panels::page>
