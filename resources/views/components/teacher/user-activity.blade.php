<section class="card w-full border border-base-300 bg-base-100 shadow-sm">
    <div
        class="card-body gap-5"
        data-teacher-user-activity-root
        data-activities-url="{{ request()->boolean('test') ? route('teacher.dashboard.user-activity.fake') : route('teacher.dashboard.user-activity') }}"
        data-empty-label="{{ __('Nessuna attività recente sugli utenti dei tuoi corsi.') }}"
        data-error-label="{{ __('Impossibile caricare le attività recenti.') }}"
    >
        <h2 class="card-title"><x-lucide-activity class="w-6 h-6" /> {{ __('Attività Recente') }}</h2>


        <div class="divide-y divide-base-300" data-teacher-user-activity-list>
            @foreach (range(1, 5) as $placeholder)
                <article class="flex items-start justify-between gap-4 py-4 first:pt-0 last:pb-0">
                    <div class="flex min-w-0 flex-1 items-start gap-4">
                        <div class="skeleton mt-1 h-4 w-4 shrink-0 rounded-full"></div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="skeleton h-6 w-56 max-w-full"></div>
                            <div class="skeleton h-4 w-40 max-w-full"></div>
                        </div>
                    </div>
                    <div class="skeleton h-5 w-16 shrink-0"></div>
                </article>
            @endforeach
        </div>

        <template data-teacher-user-activity-item-template>
            <article class="py-4 first:pt-0 last:pb-0">
                <div class="flex min-w-0 items-start gap-4">
                    <span class="mt-2 h-4 w-4 shrink-0 rounded-full" data-activity-dot></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-4">
                            <p class="min-w-0 flex-1 text-base leading-6 text-base-content/70 sm:text-base">
                                <span class="font-semibold text-base-content" data-activity-label></span>
                                <span class="text-base-content/30"> - </span>
                                <span data-activity-message></span>
                            </p>
                            <p class="shrink-0 whitespace-nowrap pt-0.5 text-right text-sm text-base-content/50" data-activity-time></p>
                        </div>
                        <p class="mt-1 truncate text-sm text-base-content/50" data-activity-context></p>
                    </div>
                </div>
            </article>
        </template>

        <div class="hidden rounded-box border border-dashed border-base-300 bg-base-200/40 px-6 py-10 text-center text-sm text-base-content/60" data-teacher-user-activity-empty></div>
    </div>
</section>

@once
    @vite('resources/js/components/teacher-user-activity.js')
@endonce
