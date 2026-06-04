<section class="card relative z-10 overflow-visible border border-base-300 bg-base-100 shadow-sm">
    <div
        class="card-body gap-6"
        data-teacher-user-engagement-root
        data-stats-url="{{ request()->boolean('test') ? route('teacher.dashboard.user-engagement.fake') : route('teacher.dashboard.user-engagement') }}"
        data-empty-label="{{ __('Nessun dato disponibile per l\'ultima settimana.') }}"
        data-error-label="{{ __('Impossibile caricare il coinvolgimento utenti.') }}"
        data-active-label="{{ __('Utenti attivi') }}"
        data-completed-label="{{ __('Completamenti') }}"
        data-active-week-label="{{ __('Attivi settimana') }}"
        data-completed-week-label="{{ __('Completati settimana') }}"
        data-active-today-label="{{ __('Attivi oggi') }}"
        data-completed-today-label="{{ __('Completati oggi') }}"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">

            <h2 class="card-title"><x-lucide-calendar class="w-6 h-6" /> {{ __('Engagement Settimanale') }}</h2>

            <div class="flex flex-wrap items-center gap-4 text-sm text-base-content/70">
                <div class="inline-flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-primary"></span>
                    <span>{{ __('Utenti attivi') }}</span>
                </div>
                <div class="inline-flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-accent"></span>
                    <span>{{ __('Completamenti') }}</span>
                </div>
            </div>
        </div>

        <div class="relative z-20 h-80 w-full overflow-visible rounded-box bg-base-200/30 p-2 sm:h-96" data-teacher-user-engagement-chart>
            <canvas class="block h-full w-full" data-teacher-user-engagement-canvas></canvas>
            <div class="pointer-events-none absolute z-30 hidden min-w-56 rounded-box border border-base-300 bg-base-100/96 p-4 shadow-xl backdrop-blur-xs" data-teacher-user-engagement-tooltip>
                <p class="text-base font-semibold text-base-content/70" data-teacher-user-engagement-tooltip-label></p>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-base-content/60" data-teacher-user-engagement-tooltip-active-label>{{ __('Utenti attivi') }}</span>
                        <span class="font-semibold text-primary" data-teacher-user-engagement-tooltip-active-value>0</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-base-content/60" data-teacher-user-engagement-tooltip-completed-label>{{ __('Completamenti') }}</span>
                        <span class="font-semibold text-accent" data-teacher-user-engagement-tooltip-completed-value>0</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="divider my-0"></div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" data-teacher-user-engagement-summary>
            @foreach (range(1, 4) as $placeholder)
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-sm">
                    <div class="skeleton mb-4 h-11 w-11 rounded-2xl"></div>
                    <div class="skeleton mb-2 h-8 w-20"></div>
                    <div class="skeleton h-4 w-28"></div>
                </div>
            @endforeach
        </div>

        <div class="hidden rounded-box border border-dashed border-base-300 bg-base-200/40 px-6 py-10 text-center text-sm text-base-content/60" data-teacher-user-engagement-empty></div>
    </div>
</section>

@once
    @vite('resources/js/components/teacher-user-engagement.js')
@endonce
