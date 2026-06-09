<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Codici NACE/ATECO')" />

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.nace-ateco.index') }}" class="flex flex-col gap-4">
                    <div class="flex w-full items-center gap-2">
                        <label class="input input-bordered flex w-full items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                name="search"
                                value="{{ $search }}"
                                class="grow"
                                placeholder="{{ __('Cerca per codice o titolo') }}"
                            >
                        </label>

                        <button type="submit" class="btn btn-primary">
                            {{ __('Cerca') }}
                        </button>

                        @if($search !== '')
                            <a href="{{ route('admin.nace-ateco.index') }}" class="btn btn-ghost">
                                {{ __('Reset') }}
                            </a>
                        @endif
                    </div>

                    @if($search !== '')
                        <div class="text-sm text-base-content/60">
                            {{ __('Trovati :count risultati su :total codici totali', ['count' => $totalCodes, 'total' => $allCodesCount]) }}
                        </div>
                    @else
                        <div class="text-sm text-base-content/60">
                            {{ __('Totale: :count codici', ['count' => $allCodesCount]) }}
                        </div>
                    @endif
                </form>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="card-title">{{ __('Gerarchia NACE/ATECO') }}</h2>
                    
                    <div class="flex gap-4 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-sm badge-primary h-fit"></span>
                            <span class="text-base-content/60">{{ __('ATECO') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-sm badge-info h-fit"></span>
                            <span class="text-base-content/60">{{ __('NACE') }}</span>
                        </div>
                    </div>
                </div>

                @if($tree->isEmpty())
                    <div class="text-center py-8 text-base-content/60">
                        {{ __('Nessun codice trovato.') }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th class="w-12">{{ __('Liv.') }}</th>
                                    <th>{{ __('Codice e Titolo') }}</th>
                                    <th class="w-32">{{ __('Rischio') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tree as $node)
                                    @include('admin.nace-ateco.partials.table-row', ['node' => $node, 'level' => 1, 'search' => $search])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
