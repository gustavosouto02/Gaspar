<div class="space-y-4">
    @php
        $demand = $getRecord();
        $comments = $demand ? $demand->comments()->with('user')->orderBy('created_at', 'desc')->get() : collect();
    @endphp

    @if($comments->isEmpty())
        <div class="text-sm text-gray-500 italic">Nenhum tratamento registrado ainda.</div>
    @else
        <div class="mt-4 space-y-6">
            @foreach($comments as $comment)
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-2">
                        <span>{{ $comment->user?->name ?? 'Sistema' }}</span>
                        @if($comment->user?->processRole)
                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-700/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                {{ $comment->user->processRole->name }}
                            </span>
                        @endif
                    </div>
                    <time class="mb-1 text-xs font-normal text-gray-400">{{ $comment->created_at->format('d/m/Y \à\s H:i') }}</time>
                    <div class="mt-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg border border-gray-100 dark:border-gray-800">
                        {!! nl2br(e($comment->comment)) !!}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
