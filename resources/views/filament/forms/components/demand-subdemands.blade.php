<div class="space-y-4">
    @php
        $demand = $getRecord();
        $subdemands = $demand ? $demand->children()->with('processStatus')->orderBy('created_at', 'desc')->get() : collect();
    @endphp

    @if($subdemands->isEmpty())
        <div class="text-sm text-gray-500 italic">Nenhuma subdemanda atrelada a esta demanda.</div>
    @else
        <ul class="list-disc pl-5 space-y-2">
            @foreach($subdemands as $subdemand)
                <li class="text-sm mb-2">
                    <a href="{{ \App\Filament\Resources\DemandResource::getUrl('view', ['record' => $subdemand->id]) }}" class="underline text-primary-600 dark:text-primary-400 font-medium">
                        #{{ strtoupper(substr($subdemand->id, 0, 8)) }} - Título: {{ $subdemand->title }} - {{ $subdemand->processStatus ? $subdemand->processStatus->name : 'Sem Situação' }};
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

</div>
