@props(['data' => []])

@php
    extract($data);
@endphp

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-4">
        <div>
            <div>
                <h2 class="card-title">{{ __('Import associazione utenti percorsi formativi recenti') }}</h2>
                <p class="text-sm text-base-content/60">{{ __('Le righe idonee vengono elaborate; quelle non idonee restano in attesa di una decisione.') }}</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Creata') }}</th>
                        <th>{{ __('Stato') }}</th>
                        <th>{{ __('Approvazioni') }}</th>
                        <th>{{ __('Azioni') }}</th>
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
                            <td>
                                @if (($importazione->pending_approvals_count ?? 0) > 0)
                                    <button
                                        type="button"
                                        class="btn btn-warning btn-sm"
                                        data-open-training-path-import-approvals
                                        data-approvals-url="{{ route('admin.imports.user-training-paths.approvals.index', $importazione) }}"
                                        data-decision-url="{{ route('admin.imports.user-training-paths.approvals.decision', $importazione) }}"
                                        data-approve-all-url="{{ route('admin.imports.user-training-paths.approvals.approve-all', $importazione) }}"
                                    >
                                        {{ __('Valuta') }}
                                    </button>
                                @else
                                    <span class="text-base-content/50">-</span>
                                @endif
                            </td>
                            <td><x-admin.imports.import-summary-actions :importazione="$importazione" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-base-content/60">
                                {{ __('Nessun import associazione utenti percorsi formativi ancora avviato.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
