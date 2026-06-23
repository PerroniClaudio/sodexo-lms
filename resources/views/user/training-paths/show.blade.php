<x-layouts.user>
    @php
        $statusLabels = [
            \App\Models\CourseEnrollment::STATUS_ASSIGNED => __('Assegnato'),
            \App\Models\CourseEnrollment::STATUS_IN_PROGRESS => __('In corso'),
            \App\Models\CourseEnrollment::STATUS_COMPLETED => __('Completato'),
            \App\Models\CourseEnrollment::STATUS_EXPIRED => __('Scaduto'),
            \App\Models\CourseEnrollment::STATUS_CANCELLED => __('Annullato'),
        ];

        $courseTypeLabels = \App\Models\Course::availableTypeLabels();
        $enrollmentsByCourseId = collect($enrollmentsByCourseId ?? []);
        $courseOrderLocks = collect($courseOrderLocks ?? []);
    @endphp

    <section class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex items-center justify-between gap-4">
            <x-page-header :title="$trainingPath->title" />
            <a href="{{ route('user.training-paths.index') }}" class="btn btn-outline btn-sm">
                <x-lucide-arrow-left class="h-4 w-4" />
                {{ __('Torna ai percorsi') }}
            </a>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        @if(filled($trainingPath->code))
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">{{ $trainingPath->code }}</p>
                        @endif
                        <h2 class="text-2xl font-semibold text-base-content">{{ __('Avanzamento percorso') }}</h2>
                    </div>
                    <span class="badge badge-outline h-fit">{{ __(':done/:total corsi completati', ['done' => $completedCourses, 'total' => $totalCourses]) }}</span>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3 text-sm text-base-content/70">
                        <span>{{ __('Completamento') }}</span>
                        <span class="text-base font-semibold text-base-content">{{ $completionPercentage }}%</span>
                    </div>
                    <progress class="progress progress-primary h-3 w-full" value="{{ $completionPercentage }}" max="100"></progress>
                </div>

                @if(filled($trainingPath->description))
                    <p class="text-sm leading-7 text-base-content/75">{{ $trainingPath->description }}</p>
                @endif
            </div>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold text-base-content">{{ __('Corsi del percorso') }}</h2>
                    <span class="badge badge-lg badge-outline h-fit">{{ $totalCourses }}</span>
                </div>

                @if($courses->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Questo percorso non contiene corsi pubblicati al momento.') }}</p>
                @else
                    <div class="space-y-3">
                        @foreach($courses as $course)
                            @php
                                $courseEnrollment = $enrollmentsByCourseId->get((int) $course->getKey());
                                $status = $courseEnrollment?->status ?? \App\Models\CourseEnrollment::STATUS_ASSIGNED;
                                $progress = (int) ($courseEnrollment?->completion_percentage ?? 0);
                                $courseLock = $courseOrderLocks->get((int) $course->getKey());
                                $isLockedByPathOrder = is_array($courseLock);
                                $typeLabel = $courseTypeLabels[$course->type] ?? strtoupper((string) $course->type);
                            @endphp

                            <article class="rounded-box border border-base-300 bg-base-100 p-4 shadow-sm">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-2 flex flex-wrap items-center gap-2">
                                            <span class="badge badge-outline badge-xs h-fit">{{ $statusLabels[$status] ?? $status }}</span>
                                            <span class="badge badge-ghost badge-xs h-fit">{{ $typeLabel }}</span>
                                        </div>
                                        <h3 class="text-base font-semibold text-base-content">{{ $course->title }}</h3>
                                    </div>

                                    @if($isLockedByPathOrder)
                                        <span class="badge badge-warning badge-outline h-fit">{{ __('Bloccato da ordine percorso') }}</span>
                                    @else
                                        <a href="{{ route('user.training-paths.courses.show', [$trainingPathEnrollment, $course]) }}" class="btn btn-primary btn-sm">
                                            {{ __('Apri corso') }}
                                        </a>
                                    @endif
                                </div>

                                @if($isLockedByPathOrder)
                                    <p class="mt-2 text-sm text-base-content/70">{{ $courseLock['message'] ?? __('Completa prima il corso corrente del percorso.') }}</p>
                                @endif

                                <div class="mt-3 flex items-center justify-between gap-2 text-xs text-base-content/60">
                                    <span>{{ __('Avanzamento corso') }}</span>
                                    <span class="font-semibold text-base-content">{{ $progress }}%</span>
                                </div>
                                <progress class="progress progress-primary mt-2 h-2 w-full" value="{{ $progress }}" max="100"></progress>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.user>
