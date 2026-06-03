<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Tipologie documento')">
            <x-slot:actions>
                <a href="{{ route('admin.document-types.create') }}" class="btn btn-primary">{{ __('Nuova tipologia') }}</a>
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="GET" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <label class="input input-bordered flex w-full max-w-xl items-center gap-2">
                        <input type="search" name="search" value="{{ $tableSearch }}" class="grow" placeholder="{{ __('Cerca nome o descrizione') }}">
                    </label>
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" name="show_trashed" value="1" class="checkbox" @checked($showTrashed)>
                        <span class="label-text">{{ __('Mostra eliminati') }}</span>
                    </label>
                    <button type="submit" class="btn btn-primary">{{ __('Cerca') }}</button>
                </form>

                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ __('Nome') }}</th>
                                <th>{{ __('Descrizione') }}</th>
                                <th>{{ __('Stato') }}</th>
                                <th class="text-right">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($documentTypes as $documentType)
                                <tr>
                                    <td>{{ $documentType->id }}</td>
                                    <td>{{ $documentType->name }}</td>
                                    <td>{{ $documentType->description ?: '—' }}</td>
                                    <td>
                                        <span class="badge {{ $documentType->trashed() ? 'badge-warning' : 'badge-success' }}">
                                            {{ $documentType->trashed() ? __('Eliminata') : __('Attiva') }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.document-types.edit', $documentType) }}" class="btn btn-sm btn-outline">{{ __('Modifica') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-sm text-base-content/70">{{ __('Nessuna tipologia documento trovata.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>{{ $documentTypes->links() }}</div>
            </div>
        </div>
    </div>
</x-layouts.admin>
