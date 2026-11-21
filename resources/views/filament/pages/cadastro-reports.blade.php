<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @if ($this->type_id)
        <div class="mt-2">
            {{ $this->table }}
        </div>
    @endif
</x-filament-panels::page>
