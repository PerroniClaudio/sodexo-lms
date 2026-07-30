@props(['recentImports', 'title'])

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-4">
        <h2 class="card-title">{{ $title }}</h2>

        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead><tr><th>{{ __('ID') }}</th><th>{{ __('Creata') }}</th><th>{{ __('Stato') }}</th><th>{{ __('Azioni') }}</th></tr></thead>
                <tbody>
                    @forelse ($recentImports as $importazione)
                        <tr>
                            <td class="font-semibold">#{{ $importazione->id }}</td>
                            <td class="text-sm">{{ $importazione->created_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                            <td><span class="badge badge-outline {{ $importazione->statusBadgeClass() }} h-fit">{{ $importazione->statusLabel() }}</span></td>
                            <td><x-admin.imports.import-summary-actions :importazione="$importazione" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-base-content/60">{{ __('Nessuna importazione ancora avviata.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
