<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Approvazioni iscrizioni ai percorsi')">
            {{ __('Log delle eccezioni approvate per corsi non assegnabili inclusi nei percorsi formativi.') }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('admin.tools.training-path-approvals.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="form-control w-full max-w-xl">
                        <span class="label-text mb-2">{{ __('Ricerca') }}</span>
                        <label class="input input-bordered flex items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input type="search" name="search" value="{{ $tableSearch }}" class="grow" placeholder="{{ __('Utente, percorso, corso o approvatore') }}">
                        </label>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ __('Filtra') }}</button>
                        <a href="{{ route('admin.tools.training-path-approvals.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-sm">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>{{ __('Data') }}</th>
                        <th>{{ __('Utente') }}</th>
                        <th>{{ __('Percorso') }}</th>
                        <th>{{ __('Corso saltato') }}</th>
                        <th>{{ __('Motivi') }}</th>
                        <th>{{ __('Approvata da') }}</th>
                        <th>{{ __('Origine') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($approvals as $approval)
                        <tr>
                            <td class="whitespace-nowrap text-sm">{{ $approval->reviewed_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                            <td>
                                <div class="font-medium">{{ $approval->user?->full_name ?? __('Utente non disponibile') }}</div>
                                <div class="text-xs text-base-content/60">{{ $approval->user?->fiscal_code ?? '-' }}</div>
                            </td>
                            <td>
                                <div class="font-medium">{{ $approval->trainingPath?->title ?? '-' }}</div>
                                <div class="text-xs text-base-content/60">{{ $approval->trainingPath?->code ?? '-' }}</div>
                            </td>
                            <td>
                                <div class="font-medium">{{ $approval->course?->title ?? '-' }}</div>
                                <div class="text-xs text-base-content/60">{{ $approval->course?->code ?? '-' }}</div>
                            </td>
                            <td class="max-w-md">
                                <ul class="list-disc space-y-1 pl-5 text-sm">
                                    @foreach ($approval->reasons as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>
                                <div class="font-medium">{{ $approval->reviewer?->full_name ?? __('Utente non disponibile') }}</div>
                                <div class="text-xs text-base-content/60">{{ $approval->reviewer?->email ?? '-' }}</div>
                            </td>
                            <td>
                                @if ($approval->importazione)
                                    <span class="badge badge-outline">{{ __('Import #:id', ['id' => $approval->importazione_id]) }}</span>
                                @else
                                    <span class="badge badge-outline">{{ __('Manuale') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-base-content/60">{{ __('Nessuna approvazione registrata.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $approvals->links() }}
    </div>
</x-layouts.admin>
