<x-layouts.admin>
    @php
        $summary = $log->summary ?? [];
        $eventTypeCounts = collect($summary['event_type_counts'] ?? []);
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Dettaglio log live stream')">
            {{ $log->module?->title ?? __('Modulo non disponibile') }}
        </x-page-header>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.live-stream-logs.index') }}" class="btn btn-ghost">
                {{ __('Torna ai log') }}
            </a>
            <a href="{{ route('admin.live-stream-logs.download', $log) }}" class="btn btn-outline">
                {{ __('Scarica JSON') }}
            </a>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Eventi') }}</p>
                    <p class="text-3xl font-semibold">{{ $log->event_count }}</p>
                </div>
            </div>
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Stats snapshot') }}</p>
                    <p class="text-3xl font-semibold">{{ $log->stats_snapshot_count }}</p>
                </div>
            </div>
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Partecipanti max') }}</p>
                    <p class="text-3xl font-semibold">{{ $log->max_participant_count }}</p>
                </div>
            </div>
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Esportato') }}</p>
                    <p class="text-sm font-semibold">{{ $log->exported_at?->format('d/m/Y H:i:s') ?? '-' }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4 p-4">
                    <h2 class="text-lg font-semibold">{{ __('Interroga eventi') }}</h2>

                    <form method="GET" action="{{ route('admin.live-stream-logs.show', $log) }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_16rem_auto] lg:items-end">
                        <label class="form-control">
                            <span class="label-text mb-2">{{ __('Ricerca testo') }}</span>
                            <label class="input input-bordered flex items-center gap-2">
                                <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                                <input type="search" name="search" value="{{ $tableSearch }}" class="grow" placeholder="{{ __('Cerca nel payload JSON') }}">
                            </label>
                        </label>

                        <label class="form-control">
                            <span class="label-text mb-2">{{ __('Tipo evento') }}</span>
                            <select name="type" class="select select-bordered">
                                <option value="">{{ __('Tutti') }}</option>
                                @foreach ($entryTypes as $entryType)
                                    <option value="{{ $entryType }}" @selected($selectedType === $entryType)>{{ $entryType }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ __('Filtra') }}</button>
                            <a href="{{ route('admin.live-stream-logs.show', $log) }}" class="btn btn-ghost">{{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="overflow-x-auto rounded-box border border-base-300">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('At') }}</th>
                                    <th>{{ __('Tipo') }}</th>
                                    <th>{{ __('Payload') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($entries as $entry)
                                    <tr class="align-top hover:bg-base-200">
                                        <td class="whitespace-nowrap text-xs">{{ data_get($entry, 'at', '-') }}</td>
                                        <td class="whitespace-nowrap font-medium">{{ data_get($entry, 'type', '-') }}</td>
                                        <td>
                                            <pre class="max-w-4xl overflow-x-auto whitespace-pre-wrap break-all text-xs">{{ json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-10 text-center text-base-content/60">
                                            {{ __('Nessun evento corrisponde ai filtri selezionati.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $entries->links() }}
                </div>
            </div>

            <div class="space-y-6">
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-3 p-4">
                        <h2 class="text-lg font-semibold">{{ __('Metadati') }}</h2>
                        <dl class="space-y-2 text-sm">
                            <div>
                                <dt class="text-base-content/50">{{ __('Docente') }}</dt>
                                <dd class="font-medium">{{ $log->teacher?->full_name ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-base-content/50">{{ __('Room Twilio') }}</dt>
                                <dd class="break-all font-medium">{{ $log->twilio_room_name ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-base-content/50">{{ __('Identity') }}</dt>
                                <dd class="break-all font-medium">{{ $log->participant_identity ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-base-content/50">{{ __('Storage') }}</dt>
                                <dd class="break-all font-medium">{{ $log->storage_reference }}</dd>
                            </div>
                            <div>
                                <dt class="text-base-content/50">{{ __('Finestra live') }}</dt>
                                <dd class="font-medium">
                                    {{ $log->started_at?->format('d/m/Y H:i:s') ?? '-' }}
                                    -
                                    {{ $log->ended_at?->format('d/m/Y H:i:s') ?? '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-3 p-4">
                        <h2 class="text-lg font-semibold">{{ __('Tipi evento') }}</h2>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($eventTypeCounts as $type => $count)
                                <span class="badge badge-outline gap-2 h-fit">{{ $type }} <span>{{ $count }}</span></span>
                            @empty
                                <span class="text-sm text-base-content/60">{{ __('Nessun riepilogo disponibile.') }}</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
