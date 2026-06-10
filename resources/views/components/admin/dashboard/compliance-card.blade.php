@props(['compliance'])

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-5">
        <h2 class="card-title">
            <x-lucide-shield-alert class="h-5 w-5" />
            {{ __('Compliance / rischio') }}
        </h2>

        <div class="stats stats-vertical border border-base-300 bg-base-100">
            <div class="stat">
                <div class="stat-title">{{ __('Utenti con requisiti mancanti') }}</div>
                <div class="stat-value text-warning">{{ $compliance['users_with_missing_requirements'] }}</div>
            </div>
            <div class="stat">
                <div class="stat-title">{{ __('Utenti con requisiti scaduti') }}</div>
                <div class="stat-value text-error">{{ $compliance['users_with_expired_requirements'] }}</div>
            </div>
        </div>

        <div class="space-y-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('Utenti critici') }}</h3>
            @forelse ($compliance['critical_users'] as $criticalUser)
                <div class="rounded-box bg-base-200 px-4 py-3">
                    <div class="font-medium">{{ $criticalUser['full_name'] }}</div>
                    <div class="mt-1 flex flex-wrap gap-2 text-xs">
                        <span class="badge badge-warning h-fit">{{ __('Mancanti: :count', ['count' => $criticalUser['missing_count']]) }}</span>
                        <span class="badge badge-error h-fit">{{ __('Scaduti: :count', ['count' => $criticalUser['expired_count']]) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-base-content/60">{{ __('Nessuna criticità rilevata.') }}</p>
            @endforelse
        </div>

        <div class="space-y-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('Requisiti più scoperti') }}</h3>
            @forelse ($compliance['top_requirements'] as $requirement)
                <div class="flex items-center justify-between gap-4 rounded-box bg-base-200 px-4 py-3">
                    <span class="text-sm">{{ $requirement['name'] }}</span>
                    <span class="badge badge-outline h-fit">{{ $requirement['affected_users_count'] }}</span>
                </div>
            @empty
                <p class="text-sm text-base-content/60">{{ __('Nessun requisito scoperto.') }}</p>
            @endforelse
        </div>
    </div>
</div>
