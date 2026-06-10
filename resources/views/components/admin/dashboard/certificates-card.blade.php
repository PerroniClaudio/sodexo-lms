@props(['certificateSummary'])

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-5">
        <div class="flex items-center justify-between gap-4">
            <h2 class="card-title">
                <x-lucide-award class="h-5 w-5" />
                {{ __('Attestati') }}
            </h2>
        </div>

        <div class="space-y-3">
            <div class="rounded-box bg-base-200 px-4 py-3">
                <p class="text-sm text-base-content/70">{{ __('Emessi ultimi 30 giorni') }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $certificateSummary['issued_last_30_days_count'] }}</p>
            </div>
            <div class="rounded-box bg-base-200 px-4 py-3">
                <p class="text-sm text-base-content/70">{{ __('In scadenza nei prossimi 30 giorni') }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $certificateSummary['expiring_next_30_days_count'] }}</p>
            </div>
            <div class="rounded-box bg-base-200 px-4 py-3">
                <p class="text-sm text-base-content/70">{{ __('Completati senza attestato') }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $certificateSummary['completed_without_certificate_count'] }}</p>
            </div>
        </div>
    </div>
</div>
