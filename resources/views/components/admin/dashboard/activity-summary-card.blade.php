@props([
    'recentResidentialWithoutDocuments',
    'compliance',
    'followUpUsers',
])

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-5">
        <h2 class="card-title">
            <x-lucide-triangle-alert class="h-5 w-5" />
            {{ __('Attività da completare') }}
        </h2>

        <div class="space-y-3">
            <div class="rounded-box bg-base-200 px-4 py-3">
                <p class="text-sm text-base-content/70">{{ __('RES recenti senza documenti') }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $recentResidentialWithoutDocuments->count() }}</p>
            </div>
            <div class="rounded-box bg-base-200 px-4 py-3">
                <p class="text-sm text-base-content/70">{{ __('Utenti con requisiti mancanti/scaduti') }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $compliance['users_with_missing_requirements'] + $compliance['users_with_expired_requirements'] }}</p>
            </div>
            <div class="rounded-box bg-base-200 px-4 py-3">
                <p class="text-sm text-base-content/70">{{ __('Utenti da sollecitare') }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $followUpUsers->count() }}</p>
            </div>
        </div>
    </div>
</div>
