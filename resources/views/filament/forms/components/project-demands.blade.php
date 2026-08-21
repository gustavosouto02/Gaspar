<div class="space-y-4">
    @php
        $project = $getRecord();
        $demands = $project ? $project->demands()->with('processStatus')->orderBy('created_at', 'desc')->get() : collect();
    @endphp

    @if($demands->isEmpty())
        <div class="text-sm text-gray-500 italic">Nenhuma demanda atrelada a este projeto.</div>
    @else
        <ul class="list-disc pl-5 space-y-2">
            @foreach($demands as $demand)
                <li class="text-sm mb-2">
                    <a href="{{ \App\Filament\Resources\DemandResource::getUrl('view', ['record' => $demand->id]) }}" class="underline text-primary-600 dark:text-primary-400 font-medium">
                        #{{ strtoupper(substr($demand->id, 0, 8)) }} - Título: {{ $demand->title }} - {{ $demand->processStatus ? $demand->processStatus->name : 'Sem Situação' }};
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

</div>
