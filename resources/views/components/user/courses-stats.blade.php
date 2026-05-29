@props(['statsUrl'])

<div class="card border border-base-300 w-full bg-base-100 card-sm shadow-sm" data-courses-stats-root data-stats-url="{{ $statsUrl }}">
    <div class="card-body">
        <h2 class="card-title"><x-lucide-chart-line class="w-6 h-6"/> {{ __('Progresso Globale') }}</h2>
        <div class="flex items-center gap-6 min-h-32" id="progress-overview">
            <div class="relative flex h-40 w-40 shrink-0 items-center justify-center sm:h-48 sm:w-48" data-courses-stats-chart aria-label="{{ __('Completamento globale dei corsi') }}">
                <canvas class="block h-full w-full" data-completion-chart></canvas>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-2xl font-semibold leading-none" data-overall-progress>0%</span>
                    <span class="text-xs text-base-content/60">{{ __('completato') }}</span>
                </div>
            </div>
            <div class="flex-1 flex flex-col gap-2" data-courses-list>
                <p class="text-sm text-base-content/60" data-courses-empty>{{ __('Caricamento dati corsi...') }}</p>
            </div>
        </div>
        <div class="mt-6">
            <p class="mb-3 text-sm font-medium text-base-content/60">Attivita settimanale (ore)</p>

            <div
                class="relative h-40 w-full max-w-3xl"
                data-weekly-activity-chart
                aria-label="Attivita settimanale sui corsi"
            >
                <canvas class="block h-full w-full" data-weekly-chart></canvas>
            </div>
        </div>
    </div>
</div>

@once
    @vite('resources/js/components/user-courses-stats.js')
@endonce
