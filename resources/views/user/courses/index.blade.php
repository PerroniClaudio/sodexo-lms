<x-layouts.user>
    @php
        $statusLabels = [
            \App\Models\CourseEnrollment::STATUS_ASSIGNED => __('Assegnato'),
            \App\Models\CourseEnrollment::STATUS_IN_PROGRESS => __('In corso'),
            \App\Models\CourseEnrollment::STATUS_COMPLETED => __('Completato'),
            \App\Models\CourseEnrollment::STATUS_EXPIRED => __('Scaduto'),
            \App\Models\CourseEnrollment::STATUS_CANCELLED => __('Annullato'),
        ];

        $statusBadges = [
            \App\Models\CourseEnrollment::STATUS_ASSIGNED => 'badge-ghost border-base-300 text-base-content/70',
            \App\Models\CourseEnrollment::STATUS_IN_PROGRESS => 'badge-primary badge-soft',
            \App\Models\CourseEnrollment::STATUS_COMPLETED => 'badge-success badge-soft',
            \App\Models\CourseEnrollment::STATUS_EXPIRED => 'badge-warning badge-soft',
            \App\Models\CourseEnrollment::STATUS_CANCELLED => 'badge-neutral badge-soft',
        ];

        $progressClasses = [
            \App\Models\CourseEnrollment::STATUS_ASSIGNED => 'progress-info',
            \App\Models\CourseEnrollment::STATUS_IN_PROGRESS => 'progress-primary',
            \App\Models\CourseEnrollment::STATUS_COMPLETED => 'progress-success',
            \App\Models\CourseEnrollment::STATUS_EXPIRED => 'progress-warning',
            \App\Models\CourseEnrollment::STATUS_CANCELLED => 'progress-neutral',
        ];

        $themeByType = [
            'fad' => [
                'dot' => 'bg-info',
                'type_badge' => 'badge-info badge-soft',
                'type_badge_classes' => 'border-sky-300 text-sky-700',
                'progress' => 'progress-info',
                'button' => 'btn-info',
            ],
            'res' => [
                'dot' => 'bg-secondary',
                'type_badge' => 'badge-secondary badge-soft',
                'type_badge_classes' => '',
                'progress' => 'progress-secondary',
                'button' => 'btn-secondary',
            ],
            'blended' => [
                'dot' => 'bg-warning',
                'type_badge' => 'badge-warning badge-soft',
                'type_badge_classes' => '',
                'progress' => 'progress-warning',
                'button' => 'btn-warning',
            ],
            'fsc' => [
                'dot' => 'bg-secondary',
                'type_badge' => 'badge-secondary badge-soft',
                'type_badge_classes' => '',
                'progress' => 'progress-secondary',
                'button' => 'btn-secondary',
            ],
            'async' => [
                'dot' => 'bg-primary',
                'type_badge' => 'badge-primary badge-soft',
                'type_badge_classes' => '',
                'progress' => 'progress-primary',
                'button' => 'btn-primary',
            ],
            'unknown' => [
                'dot' => 'bg-neutral',
                'type_badge' => 'badge-neutral badge-soft',
                'type_badge_classes' => '',
                'progress' => 'progress-neutral',
                'button' => 'btn-neutral',
            ],
        ];

        $courseTypeLabels = \App\Models\Course::availableTypeLabels();
        $totalCourses = $enrollments->count();
        $completedCourses = $enrollments->where('status', \App\Models\CourseEnrollment::STATUS_COMPLETED)->count();
        $activeCourses = $enrollments
            ->whereIn('status', [
                \App\Models\CourseEnrollment::STATUS_ASSIGNED,
                \App\Models\CourseEnrollment::STATUS_IN_PROGRESS,
            ])
            ->count();
        $averageProgress = (int) round($enrollments->avg('completion_percentage') ?? 0);
    @endphp

    <section class="flex min-h-full w-full flex-col gap-5 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('I miei corsi')" />

        @if($enrollments->isEmpty())
            <div class="card border border-dashed border-base-300 bg-base-100 shadow-sm">
                <div class="card-body items-center gap-4 py-14 text-center">
                    <div class="flex size-16 items-center justify-center rounded-full bg-base-200 text-base-content/60">
                        <x-lucide-book-open class="h-8 w-8" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-base-content">{{ __('Nessun corso assegnato') }}</h2>
                        <p class="mt-1 text-sm text-base-content/60">{{ __('Non sei iscritto a nessun corso al momento.') }}</p>
                    </div>
                </div>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-3">
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-2 p-5">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-base-content/60">{{ __('Da seguire') }}</span>
                            <x-lucide-play-circle class="h-5 w-5 text-primary" />
                        </div>
                        <p class="text-3xl font-semibold text-base-content">{{ $activeCourses }}</p>
                    </div>
                </div>
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-2 p-5">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-base-content/60">{{ __('Completati') }}</span>
                            <x-lucide-award class="h-5 w-5 text-success" />
                        </div>
                        <p class="text-3xl font-semibold text-base-content">{{ $completedCourses }}</p>
                    </div>
                </div>
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-2 p-5">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-base-content/60">{{ __('Avanzamento medio') }}</span>
                            <x-lucide-gauge class="h-5 w-5 text-secondary" />
                        </div>
                        <p class="text-3xl font-semibold text-base-content">{{ $averageProgress }}%</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold text-base-content">{{ __('Lista corsi') }}</h2>

                <label class="input input-bordered flex w-full items-center gap-2 bg-base-100 sm:max-w-sm">
                    <x-lucide-search class="h-4 w-4 text-base-content/40" />
                    <input
                        type="search"
                        class="grow"
                        placeholder="{{ __('Cerca corso') }}"
                        aria-label="{{ __('Cerca corso') }}"
                        data-courses-search
                    />
                </label>
            </div>

            <div class="flex flex-col gap-4" data-courses-list>
                @foreach($enrollments as $enrollment)
                    @php
                        $course = $enrollment->course;
                        $progress = max(0, min(100, (int) $enrollment->completion_percentage));
                        $statusBadge = $statusBadges[$enrollment->status] ?? 'badge-ghost';
                        $progressClass = $progressClasses[$enrollment->status] ?? 'progress-primary';
                        $typeLabel = $course?->type !== null
                            ? ($courseTypeLabels[$course->type] ?? strtoupper((string) $course->type))
                            : null;
                        $theme = $themeByType[$course?->type] ?? $themeByType['unknown'];
                    @endphp

                    <article
                        class="rounded-box border border-base-300 bg-base-100 p-4 shadow-sm transition hover:border-primary/30"
                        data-course-item
                        data-search-text="{{ str($course?->title.' '.$typeLabel.' '.$course?->categories?->pluck('name')->implode(' ').' '.($statusLabels[$enrollment->status] ?? $enrollment->status))->lower() }}"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 flex-1 gap-3">
                                <div class="mt-1 h-3 w-3 shrink-0 rounded-full {{ $theme['dot'] }}"></div>

                                <div class="min-w-0 flex-1">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span class="badge badge-xs {{ $statusBadge }} h-fit">
                                            {{ $statusLabels[$enrollment->status] ?? __(str_replace('_', ' ', $enrollment->status)) }}
                                        </span>
                                        @if($typeLabel !== null)
                                            <span class="badge badge-xs {{ $theme['type_badge'] }} {{ $theme['type_badge_classes'] }}">{{ $typeLabel }}</span>
                                        @endif
                                    </div>

                                    <h2 class="text-base font-semibold leading-snug text-base-content">
                                        {{ $course?->title ?? __('Corso non disponibile') }}
                                    </h2>

                                    @if($course?->categories?->isNotEmpty())
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach($course->categories->take(3) as $courseCategory)
                                                <span class="badge badge-outline badge-primary badge-xs h-fit">{{ $courseCategory->name }}</span>
                                            @endforeach
                                            @if($course->categories->count() > 3)
                                                <span class="badge badge-ghost badge-xs h-fit">+{{ $course->categories->count() - 3 }}</span>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-base-content/60">
                                        @if($enrollment->last_accessed_at !== null)
                                            <span>{{ __('Ultimo accesso') }}: {{ $enrollment->last_accessed_at->format('d/m/Y') }}</span>
                                        @endif

                                        @if($enrollment->expires_at !== null)
                                            <span>{{ __('Scadenza') }}: {{ $enrollment->expires_at->format('d/m/Y') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if($course !== null)
                                <a
                                    href="{{ route('user.courses.show', $course) }}"
                                    class="btn btn-circle btn-sm btn-outline shrink-0 {{ $theme['button'] }}"
                                    aria-label="{{ __('Apri corso :title', ['title' => $course->title]) }}"
                                >
                                    <x-lucide-play class="h-3.5 w-3.5" />
                                </a>
                            @endif
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-base-content/60">
                            <span>{{ __('Avanzamento') }}</span>
                            <span class="font-semibold text-base-content">{{ $progress }}%</span>
                        </div>

                        <progress class="progress mt-2 h-2 w-full {{ $theme['progress'] ?? $progressClass }}" value="{{ $progress }}" max="100"></progress>
                    </article>
                @endforeach
            </div>

            <div class="hidden rounded-box border border-dashed border-base-300 bg-base-100 px-6 py-10 text-center text-sm text-base-content/60" data-courses-search-empty>
                {{ __('Nessun corso trovato.') }}
            </div>
        @endif
    </section>

    @once
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const searchInput = document.querySelector('[data-courses-search]');
                const courseItems = Array.from(document.querySelectorAll('[data-course-item]'));
                const emptyState = document.querySelector('[data-courses-search-empty]');

                if (!(searchInput instanceof HTMLInputElement) || !(emptyState instanceof HTMLElement)) {
                    return;
                }

                searchInput.addEventListener('input', () => {
                    const query = searchInput.value.trim().toLowerCase();
                    let visibleCount = 0;

                    courseItems.forEach((item) => {
                        if (!(item instanceof HTMLElement)) {
                            return;
                        }

                        const matches = query === '' || item.dataset.searchText?.includes(query);
                        item.classList.toggle('hidden', !matches);

                        if (matches) {
                            visibleCount += 1;
                        }
                    });

                    emptyState.classList.toggle('hidden', visibleCount > 0);
                });
            });
        </script>
    @endonce
</x-layouts.user>
