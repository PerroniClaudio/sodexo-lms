@props(['recentResidentialWithoutDocuments'])

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-5">
        <h2 class="card-title">
            <x-lucide-file-warning class="h-5 w-5" />
            {{ __('RES senza documenti') }}
        </h2>

        <div class="space-y-3">
            @forelse ($recentResidentialWithoutDocuments as $resModule)
                <div class="rounded-box bg-base-200 px-4 py-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-medium">{{ $resModule['module_title'] }}</p>
                            <p class="text-sm text-base-content/70">{{ $resModule['course_title'] }}</p>
                        </div>
                        <span class="badge badge-warning h-fit">{{ __(':count partecipanti', ['count' => $resModule['participants_count']]) }}</span>
                    </div>
                    <p class="mt-2 text-xs text-base-content/60">{{ __('Ultima sessione conclusa: :date', ['date' => $resModule['latest_schedule_end_label']]) }}</p>
                </div>
            @empty
                <p class="text-sm text-base-content/60">{{ __('Nessun RES recente senza documenti caricati.') }}</p>
            @endforelse
        </div>
    </div>
</div>
