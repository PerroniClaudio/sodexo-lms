<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Log live stream')">
            {{ __('Archivio dei log esportati dalla view docente a fine diretta.') }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4 p-4">
                <form method="GET" action="{{ route('admin.live-stream-logs.index') }}" class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <label class="form-control w-full lg:max-w-md">
                        <span class="label-text mb-2">{{ __('Ricerca') }}</span>
                        <label class="input input-bordered flex items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                name="search"
                                value="{{ $tableSearch }}"
                                class="grow"
                                placeholder="{{ __('Modulo, docente, room name o ID') }}"
                            >
                        </label>
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ __('Filtra') }}</button>
                        <a href="{{ route('admin.live-stream-logs.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-sm">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Modulo') }}</th>
                        <th>{{ __('Docente') }}</th>
                        <th>{{ __('Room') }}</th>
                        <th>{{ __('Eventi') }}</th>
                        <th>{{ __('Partecipanti max') }}</th>
                        <th>{{ __('Esportato') }}</th>
                        <th>{{ __('Azioni') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="hover:bg-base-200">
                            <td class="font-semibold">{{ $log->id }}</td>
                            <td>
                                <div class="font-medium">{{ $log->module?->title ?? __('Modulo non disponibile') }}</div>
                                <div class="text-xs text-base-content/60">{{ __('Sessione #:id', ['id' => $log->live_stream_session_id]) }}</div>
                            </td>
                            <td>{{ $log->teacher?->full_name ?? __('Docente non disponibile') }}</td>
                            <td class="max-w-xs truncate">{{ $log->twilio_room_name ?: '-' }}</td>
                            <td>
                                <div class="font-medium">{{ $log->event_count }}</div>
                                <div class="text-xs text-base-content/60">{{ __('Stats: :count', ['count' => $log->stats_snapshot_count]) }}</div>
                            </td>
                            <td>{{ $log->max_participant_count }}</td>
                            <td>{{ $log->exported_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.live-stream-logs.show', $log) }}" class="btn btn-sm btn-primary">
                                        {{ __('Apri') }}
                                    </a>
                                    <a href="{{ route('admin.live-stream-logs.download', $log) }}" class="btn btn-sm btn-outline">
                                        {{ __('JSON') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-base-content/60">
                                {{ __('Nessun log live stream disponibile.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </div>
</x-layouts.admin>
