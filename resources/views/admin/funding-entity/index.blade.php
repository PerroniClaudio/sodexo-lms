<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Enti finanziatori')">
            <x-slot:actions>
                <a href="{{ route('admin.funding-entities.create') }}" class="btn btn-primary">{{ __('Nuovo ente') }}</a>
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="GET" action="{{ route('admin.funding-entities.index') }}" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <label class="label cursor-pointer justify-start gap-3 p-0">
                        <input type="checkbox" name="show_trashed" value="1" class="checkbox" @checked($showTrashed) onchange="this.form.submit()">
                        <span class="label-text">{{ __('Mostra eliminati') }}</span>
                    </label>

                    <div class="flex w-full max-w-xl items-center gap-2">
                        <label class="input input-bordered flex w-full items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                name="search"
                                class="grow"
                                value="{{ $tableSearch }}"
                                placeholder="{{ __('Cerca ragione sociale, P.IVA, CF o PEC') }}"
                            >
                        </label>
                        <input type="hidden" name="sort" value="{{ $tableSort }}">
                        <input type="hidden" name="direction" value="{{ $tableDirection }}">
                        <button type="submit" class="btn btn-primary">{{ __('Cerca') }}</button>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th><a href="{{ route('admin.funding-entities.index', ['search' => $tableSearch, 'show_trashed' => $showTrashed ? 1 : 0, 'sort' => 'id', 'direction' => $tableSort === 'id' && $tableDirection === 'asc' ? 'desc' : 'asc']) }}">{{ __('ID') }}</a></th>
                                <th><a href="{{ route('admin.funding-entities.index', ['search' => $tableSearch, 'show_trashed' => $showTrashed ? 1 : 0, 'sort' => 'company_name', 'direction' => $tableSort === 'company_name' && $tableDirection === 'asc' ? 'desc' : 'asc']) }}">{{ __('Ragione Sociale') }}</a></th>
                                <th>{{ __('Partita IVA') }}</th>
                                <th>{{ __('Codice Fiscale') }}</th>
                                <th>{{ __('PEC') }}</th>
                                <th>{{ __('Stato') }}</th>
                                <th class="text-right">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($fundingEntities as $fundingEntity)
                                <tr>
                                    <td>{{ $fundingEntity->id }}</td>
                                    <td class="font-medium">{{ $fundingEntity->company_name }}</td>
                                    <td>{{ $fundingEntity->vat_number ?: '—' }}</td>
                                    <td>{{ $fundingEntity->fiscal_code ?: '—' }}</td>
                                    <td>{{ $fundingEntity->pec ?: '—' }}</td>
                                    <td>
                                        <span class="badge {{ $fundingEntity->trashed() ? 'badge-outline badge-error' : 'badge-outline badge-success' }}">
                                            {{ $fundingEntity->trashed() ? __('Eliminato') : __('Attivo') }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.funding-entities.edit', $fundingEntity) }}" class="btn btn-primary btn-sm">{{ __('Modifica') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-base-content/60">{{ __('Nessun ente finanziatore trovato.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $fundingEntities->links() }}
            </div>
        </div>
    </div>
</x-layouts.admin>
