<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-4">
        <div>
            <div>
                <h2 class="card-title">{{ __('Import utenti recenti') }}</h2>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Creata') }}</th>
                        <th>{{ __('Stato') }}</th>
                        <th>{{ __('File') }}</th>
                        <th>{{ __('Errore') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentImports as $importazione)
                        <tr>
                            <td class="font-semibold">#{{ $importazione->id }}</td>
                            <td class="text-sm">{{ $importazione->created_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                            <td>
                                <span class="badge badge-outline {{ $importazione->statusBadgeClass() }} h-fit">
                                    {{ $importazione->statusLabel() }}
                                </span>
                            </td>
                            <td class="max-w-sm">
                                <div class="truncate font-medium">{{ $importazione->fileName() }}</div>
                            </td>
                            <td class="max-w-md text-sm">
                                @if ($importazione->error_message)
                                    <div class="rounded-box border border-error/30 bg-error/10 p-2">
                                        {{ $importazione->error_message }}
                                    </div>
                                @else
                                    <span class="text-base-content/50">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-base-content/60">
                                {{ __('Nessun import utenti ancora avviato.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
