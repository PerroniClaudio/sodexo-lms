@props([
    'columns' => [],
    'rows',
    'sort' => null,
    'direction' => 'asc',
    'search' => '',
    'searchPlaceholder' => __('Cerca'),
    'emptyMessage' => __('Nessun elemento disponibile.'),
    'showSearch' => true,
])

@php
    $currentDirection = $direction === 'asc' ? 'asc' : 'desc';
    $hasRows = $rows->count() > 0;
@endphp

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-4">
        @isset($filters)
            {{ $filters }}
        @endisset

        @if($showSearch)
        <div class="flex justify-end">
            <form method="GET" class="flex w-full max-w-md items-center gap-2">
                @foreach (request()->query() as $key => $value)
                    @continue(in_array($key, ['search', 'page'], true))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <label class="input input-bordered flex w-full items-center gap-2">
                    <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        class="grow"
                        placeholder="{{ $searchPlaceholder }}"
                    >
                </label>

                <button type="submit" class="btn btn-primary">
                    {{ __('Cerca') }}
                </button>
            </form>
        </div>
        @endif

        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            @php
                                $columnKey = $column['key'];
                                $columnIsSortable = $column['sortable'] ?? false;
                                $columnIsActive = $sort === $columnKey;
                                
                                // Ciclo di ordinamento a 3 stati: ASC → DESC → Nessun ordinamento
                                if (!$columnIsActive) {
                                    // Colonna non attiva → ASC
                                    $nextDirection = 'asc';
                                    $nextSort = $columnKey;
                                } elseif ($columnIsActive && $currentDirection === 'asc') {
                                    // ASC → DESC
                                    $nextDirection = 'desc';
                                    $nextSort = $columnKey;
                                } else {
                                    // DESC → Rimuovi ordinamento (torna al default)
                                    $nextDirection = null;
                                    $nextSort = null;
                                }
                                
                                // Genera URL con o senza parametri di ordinamento
                                if ($nextSort && $nextDirection) {
                                    $columnUrl = request()->fullUrlWithQuery([
                                        'sort' => $nextSort,
                                        'direction' => $nextDirection,
                                        'page' => null,
                                    ]);
                                } else {
                                    // Rimuovi parametri di ordinamento per tornare al default
                                    $params = request()->query();
                                    unset($params['sort'], $params['direction'], $params['page']);
                                    $columnUrl = request()->url() . ($params ? '?' . http_build_query($params) : '');
                                }
                            @endphp

                            <th class="{{ $column['class'] ?? '' }}">
                                @if ($columnIsSortable)
                                    <a href="{{ $columnUrl }}" class="inline-flex items-center gap-2">
                                        <span>{{ $column['label'] }}</span>

                                        @if ($columnIsActive)
                                            @if ($currentDirection === 'asc')
                                                <x-lucide-chevron-up class="h-4 w-4" />
                                            @else
                                                <x-lucide-chevron-down class="h-4 w-4" />
                                            @endif
                                        @else
                                            <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" />
                                        @endif
                                    </a>
                                @else
                                    {{ $column['label'] }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @if ($hasRows)
                        {{ $slot }}
                    @else
                        <tr>
                            <td colspan="{{ count($columns) }}" class="py-8 text-center text-base-content/70">
                                {{ $emptyMessage }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="flex flex-col gap-4 border-t border-base-300 pt-4 lg:flex-row lg:items-center lg:justify-between">
                <p class="text-sm text-base-content/70">
                    {{ __('Visualizzazione di :from-:to su :total risultati', ['from' => $rows->firstItem(), 'to' => $rows->lastItem(), 'total' => $rows->total()]) }}
                </p>

                <div class="join self-start lg:self-auto">
                    <a
                        href="{{ $rows->previousPageUrl() ?? '#' }}"
                        @class([
                            'join-item btn',
                            'btn-disabled pointer-events-none' => $rows->onFirstPage(),
                        ])
                    >
                        {{ __('Precedente') }}
                    </a>

                    @foreach ($rows->getUrlRange(1, $rows->lastPage()) as $page => $url)
                        <a
                            href="{{ $url }}"
                            @class([
                                'join-item btn',
                                'btn-active' => $page === $rows->currentPage(),
                            ])
                        >
                            {{ $page }}
                        </a>
                    @endforeach

                    <a
                        href="{{ $rows->nextPageUrl() ?? '#' }}"
                        @class([
                            'join-item btn',
                            'btn-disabled pointer-events-none' => ! $rows->hasMorePages(),
                        ])
                    >
                        {{ __('Successiva') }}
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
