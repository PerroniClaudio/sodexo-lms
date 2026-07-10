<x-layouts.admin>
    @php
        $columns = [
            ['key' => 'id', 'label' => __('ID'), 'sortable' => true],
            ['key' => 'name', 'label' => __('Nome'), 'sortable' => true],
            ['key' => 'rules', 'label' => __('Regole'), 'sortable' => false],
            ['key' => 'is_active', 'label' => __('Stato'), 'sortable' => true],
            ['key' => 'actions', 'label' => __('Azioni'), 'sortable' => false],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Requisiti (Ruolo / Mansione)')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.job-based-requirements.create')"
        />

        <x-data-table
            :columns="$columns"
            :rows="$jobBasedRequirements"
            :sort="$tableSort"
            :direction="$tableDirection"
            :search="$tableSearch"
            :search-placeholder="__('Cerca nei requisiti ruolo/mansione')"
            :empty-message="__('Nessun requisito ruolo/mansione disponibile.')"
            :show-search="false"
        >
            <x-slot:filters>
                <form method="GET" action="{{ route('admin.job-based-requirements.index') }}" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input
                                type="checkbox"
                                name="show_trashed"
                                value="1"
                                class="checkbox"
                                @checked($showTrashed)
                                onchange="this.form.submit()"
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
                            placeholder="{{ __('Cerca nei requisiti ruolo/mansione') }}"
                            class="input input-bordered w-full"
                        >
                        <button type="submit" class="btn btn-primary">
                            {{ __('Cerca') }}
                        </button>
                    </div>
                </form>
            </x-slot:filters>

            @foreach ($jobBasedRequirements as $jobBasedRequirement)
                <tr class="hover:bg-base-200">
                    <td class="text-sm">{{ $jobBasedRequirement->id }}</td>
                    <td>
                        <div class="flex flex-col gap-1">
                            <span class="font-medium">{{ $jobBasedRequirement->name }}</span>
                            @if ($jobBasedRequirement->description)
                                <span class="text-sm text-base-content/70">{{ Str::limit($jobBasedRequirement->description, 70) }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="flex flex-col gap-2">
                            @foreach ($jobBasedRequirement->rules ?? [] as $groupIndex => $group)
                                <div class="rounded-box border border-base-300 bg-base-100 px-3 py-2 text-sm">
                                    <span class="badge badge-primary badge-soft">{{ __('OR') }} {{ $groupIndex + 1 }}</span>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($group as $condition)
                                            <span class="badge badge-outline h-fit">
                                                {{ ($condition['field'] ?? '') === 'job_role_id' ? __('Ruolo') : __('Mansione') }}
                                                {{ $condition['operator'] ?? '' }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </td>
                    <td>
                        @if ($jobBasedRequirement->trashed())
                            <span class="badge badge-error badge-soft h-fit">{{ __('Eliminato') }}</span>
                        @elseif ($jobBasedRequirement->is_active)
                            <span class="badge badge-success badge-soft h-fit">{{ __('Attivo') }}</span>
                        @else
                            <span class="badge badge-warning badge-soft h-fit">{{ __('Disattivato') }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center gap-2">
                            @if ($jobBasedRequirement->trashed())
                                <form method="POST" action="{{ route('admin.job-based-requirements.restore', $jobBasedRequirement->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <x-lucide-undo class="h-4 w-4" />
                                        {{ __('Ripristina') }}
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('admin.job-based-requirements.edit', $jobBasedRequirement) }}" class="btn btn-ghost btn-sm">
                                    <x-lucide-pencil class="h-4 w-4" />
                                    {{ __('Modifica') }}
                                </a>
                                <form method="POST" action="{{ route('admin.job-based-requirements.destroy', $jobBasedRequirement) }}" class="inline" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questo requisito?') }}')">
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
</x-layouts.admin>
