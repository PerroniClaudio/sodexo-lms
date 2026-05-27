<x-layouts.admin>
    @php
        $columns = [
            ['key' => 'id', 'label' => __('ID'), 'sortable' => true],
            ['key' => 'name', 'label' => __('Nome'), 'sortable' => true],
            ['key' => 'risk_levels', 'label' => __('Livelli di rischio'), 'sortable' => false],
            ['key' => 'validity', 'label' => __('Validità'), 'sortable' => true],
            ['key' => 'actions', 'label' => __('Azioni'), 'sortable' => false],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Requisiti (Rischio)')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.risk-based-requirements.create')"
        />

        <x-data-table
            :columns="$columns"
            :rows="$riskBasedRequirements"
            :sort="$tableSort"
            :direction="$tableDirection"
            :search="$tableSearch"
            :search-placeholder="__('Cerca nei requisiti di rischio')"
            :empty-message="__('Nessun requisito di rischio disponibile.')"
            :show-search="false"
        >
            <x-slot:filters>
                <form method="GET" action="{{ route('admin.risk-based-requirements.index') }}" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input
                                type="checkbox"
                                name="show_trashed"
                                value="1"
                                class="checkbox"
                                @checked($showTrashed)
                                onchange="handleShowTrashedChange(this)"
                            >
                            <span class="label-text">{{ __('Mostra eliminati') }}</span>
                        </label>
                    </div>

                    <div class="flex w-full max-w-md items-center gap-2">
                        @foreach (request()->query() as $key => $value)
                            @continue(in_array($key, ['search', 'page', 'show_trashed'], true))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <input
                            type="search"
                            name="search"
                            value="{{ $tableSearch }}"
                            placeholder="{{ __('Cerca nei requisiti di rischio') }}"
                            class="input input-bordered w-full"
                        >
                        <button type="submit" class="btn btn-primary">
                            {{ __('Cerca') }}
                        </button>
                    </div>
                </form>
            </x-slot:filters>

            @foreach ($riskBasedRequirements as $riskBasedRequirement)
                <tr class="hover:bg-base-200">
                    <td class="text-sm">{{ $riskBasedRequirement->id }}</td>
                    <td>
                        <div class="flex flex-col gap-1">
                            <span class="font-medium">{{ $riskBasedRequirement->name }}</span>
                            @if($riskBasedRequirement->description)
                                <span class="text-sm text-base-content/70">{{ Str::limit($riskBasedRequirement->description, 60) }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach($riskBasedRequirement->risk_levels as $level)
                                <span class="badge {{ $level->badgeColor() }} badge-sm">
                                    {{ $level->label() }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="text-sm">
                        <div class="flex items-center gap-2">
                            @if($riskBasedRequirement->is_limited_validity)
                                <x-lucide-clock class="h-4 w-4 text-warning" />
                                <span>{{ $riskBasedRequirement->getValidityDescription() }}</span>
                            @else
                                <x-lucide-infinity class="h-4 w-4 text-success" />
                                <span>{{ __('Illimitata') }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="flex items-center gap-2">
                            @if($riskBasedRequirement->trashed())
                                <form method="POST" action="{{ route('admin.risk-based-requirements.restore', $riskBasedRequirement->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <x-lucide-undo class="h-4 w-4" />
                                        {{ __('Ripristina') }}
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('admin.risk-based-requirements.edit', $riskBasedRequirement) }}" class="btn btn-ghost btn-sm">
                                    <x-lucide-pencil class="h-4 w-4" />
                                    {{ __('Modifica') }}
                                </a>
                                <form method="POST" action="{{ route('admin.risk-based-requirements.destroy', $riskBasedRequirement) }}" class="inline" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questo requisito di rischio?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-error btn-sm">
                                        <x-lucide-trash class="h-4 w-4" />
                                        {{ __('Elimina') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    @push('scripts')
    <script>
        function handleShowTrashedChange(checkbox) {
            checkbox.closest('form').submit();
        }
    </script>
    @endpush
</x-layouts.admin>
