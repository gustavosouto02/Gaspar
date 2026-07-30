<div class="flex flex-wrap items-center gap-3 mr-4">
    <a href="{{ \App\Filament\Resources\DemandResource::getUrl('create') }}"
       class="fi-btn fi-btn-size-sm inline-flex items-center justify-center gap-1.5 rounded-lg px-3 h-8 text-sm font-semibold shadow-sm ring-1 ring-gray-950/10 bg-primary-600 text-white hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400 transition">
        <x-heroicon-m-plus-circle class="h-4 w-4" />
        Nova demanda
    </a>

    <form action="{{ \App\Filament\Pages\DemandReports::getUrl() }}" method="GET" 
          class="inline-flex items-center justify-center gap-2 rounded-lg px-3 h-8 shadow-sm ring-1 ring-inset ring-gray-950/10 bg-white dark:bg-white/5 transition duration-75 focus-within:ring-2 focus-within:ring-primary-600">
        <x-heroicon-m-magnifying-glass class="h-4 w-4 text-gray-500 dark:text-gray-400" />
        <input type="search" name="tableSearch" placeholder="Pesquisar demanda..." 
               value="{{ request('tableSearch') }}"
               class="block w-48 h-full border-none bg-transparent p-0 text-sm text-left placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-500">
    </form>
</div>
