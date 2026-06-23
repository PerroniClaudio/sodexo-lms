<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Monitor importazioni')">
            {{ __('Storico importazioni con stato, errori, file caricato e utente che ha avviato il processo.') }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4 p-4">
                <form method="GET" action="{{ route('admin.importazioni-monitor.index') }}" class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <label class="form-control w-full">
                            <span class="label-text mb-2">{{ __('Tipo') }}</span>
                            <select name="type" class="select select-bordered">
                                <option value="">{{ __('Tutti') }}</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected($selectedType === $type)>
                                        {{ match ($type) {
                                            'utenti' => __('Utenti'),
                                            'unita_lavorative' => __('Unità lavorative'),
                                            'mansioni' => __('Mansioni'),
                                            default => $type,
                                        } }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="form-control w-full">
                            <span class="label-text mb-2">{{ __('Stato') }}</span>
                            <select name="status" class="select select-bordered">
                                <option value="">{{ __('Tutti') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($selectedStatus === $status)>
                                        {{ match ($status) {
                                            'pending' => __('In coda'),
                                            'progress' => __('In lavorazione'),
                                            'finished' => __('Completata'),
                                            'failed' => __('Fallita'),
                                            default => $status,
                                        } }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="form-control w-full">
                            <span class="label-text mb-2">{{ __('Ricerca') }}</span>
                            <label class="input input-bordered flex items-center gap-2">
                                <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                                <input
                                    type="search"
                                    name="search"
                                    value="{{ $tableSearch }}"
                                    class="grow"
                                    placeholder="{{ __('ID, file, utente o errore') }}"
                                >
                            </label>
                        </label>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ __('Filtra') }}</button>
                        <a href="{{ route('admin.importazioni-monitor.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-sm">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Tipo') }}</th>
                        <th>{{ __('Creata da') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Stato') }}</th>
                        <th>{{ __('File') }}</th>
                        <th>{{ __('Errore') }}</th>
                        <th>{{ __('Azioni') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($importazioni as $importazione)
                        <tr class="hover:bg-base-200">
                            <td class="font-semibold">{{ $importazione->id }}</td>
                            <td>{{ $importazione->typeLabel() }}</td>
                            <td>
                                <div class="font-medium">{{ $importazione->creator?->full_name ?? __('Utente non disponibile') }}</div>
                                <div class="text-xs text-base-content/60">{{ $importazione->creator?->email ?? '-' }}</div>
                            </td>
                            <td class="text-sm">
                                <div>{{ __('Creata: :date', ['date' => $importazione->created_at?->format('d/m/Y H:i:s') ?? '-']) }}</div>
                                <div class="text-base-content/60">{{ __('Inizio: :date', ['date' => $importazione->started_at?->format('d/m/Y H:i:s') ?? '-']) }}</div>
                                <div class="text-base-content/60">{{ __('Fine: :date', ['date' => $importazione->finished_at?->format('d/m/Y H:i:s') ?? '-']) }}</div>
                            </td>
                            <td>
                                <span class="badge badge-outline {{ $importazione->statusBadgeClass() }} h-fit">
                                    {{ $importazione->statusLabel() }}
                                </span>
                            </td>
                            <td class="max-w-md">
                                <div class="truncate font-medium">{{ $importazione->fileName() }}</div>
                                <div class="break-all text-xs text-base-content/60">{{ $importazione->file_path }}</div>
                            </td>
                            <td class="max-w-md">
                                @if ($importazione->error_message)
                                    <div class="rounded-box border border-error/30 bg-error/10 p-3 text-sm text-base-content">
                                        {{ $importazione->error_message }}
                                    </div>
                                @else
                                    <span class="text-base-content/50">-</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.importazioni-monitor.download', $importazione) }}" class="btn btn-sm btn-outline">
                                    {{ __('Scarica file') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-base-content/60">
                                {{ __('Nessuna importazione disponibile.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $importazioni->links() }}
    </div>
</x-layouts.admin>
