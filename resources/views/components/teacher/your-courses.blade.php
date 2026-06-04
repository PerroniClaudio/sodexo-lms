<section class="card border border-base-300 bg-base-100 shadow-sm">
    <div
        class="card-body gap-6"
        data-teacher-your-courses-root
        data-courses-url="{{ request()->boolean('test') ? route('teacher.dashboard.your-courses.fake') : route('teacher.dashboard.your-courses') }}"
        data-empty-label="{{ __('Nessuna classe assegnata al momento.') }}"
        data-error-label="{{ __('Impossibile caricare i corsi del docente.') }}"
        data-count-label="{{ __('classi') }}"
        data-capacity-label="{{ __('Capienza classe') }}"
        data-progress-label="{{ __('Completamento corso') }}"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="card-title"><x-lucide-book-open-text class="w-6 h-6" /> {{ __('I tuoi corsi') }}</h2>
            <div class="badge badge-neutral badge-outline badge-lg" data-teacher-your-courses-count>0 {{ __('classi') }}</div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-teacher-your-courses-list>
            @foreach (range(1, 3) as $placeholder)
                <div class="card overflow-hidden border border-base-300 bg-base-100 shadow-sm">
                    <div class="h-36 animate-pulse bg-linear-to-r from-base-300 to-base-200"></div>
                    <div class="card-body gap-4">
                        <div class="skeleton h-4 w-20"></div>
                        <div class="skeleton h-8 w-3/4"></div>
                        <div class="space-y-3">
                            <div class="skeleton h-4 w-1/2"></div>
                            <div class="skeleton h-4 w-2/3"></div>
                        </div>
                        <div class="space-y-2">
                            <div class="skeleton h-2 w-full"></div>
                            <div class="skeleton h-4 w-24"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <template data-teacher-your-courses-card-template>
            <article class="card overflow-hidden border border-base-300 bg-base-100 shadow-sm">
                <div class="flex h-36 items-start justify-between p-5" data-course-cover>
                    <span class="badge badge-lg border-base-100/40 bg-base-100/90 px-4 text-sm font-medium text-base-content shadow-sm backdrop-blur-xs" data-course-class-badge>Classe</span>
                </div>

                <div class="card-body gap-4">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em]" data-course-type-label>Corso</p>
                    <h3 class="text-2xl font-semibold leading-tight text-base-content" data-course-title>Corso senza titolo</h3>

                    <div class="flex flex-col gap-3 text-sm text-base-content/70">
                        <div class="inline-flex items-center gap-2" data-course-participants>
                            <span data-course-participants-icon></span>
                            <span data-course-participants-text>0 partecipanti</span>
                        </div>

                        <div class="inline-flex items-center gap-2" data-course-capacity>
                            <span data-course-capacity-icon></span>
                            <span data-course-capacity-text>Capienza classe: 0/30 posti</span>
                        </div>
                    </div>

                    <div class="space-y-2" data-course-progress>
                        <progress class="progress w-full" max="100" value="0" data-course-progress-bar></progress>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em]" data-course-progress-text>Completamento corso: 0%</p>
                    </div>
                </div>
            </article>
        </template>

        <div class="hidden rounded-box border border-dashed border-base-300 bg-base-200/40 px-6 py-10 text-center text-sm text-base-content/60" data-teacher-your-courses-empty></div>
    </div>
</section>

@once
    @vite('resources/js/components/teacher-your-courses.js')
@endonce
