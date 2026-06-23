<x-layouts.user>
    @php
        $enrollments = collect($enrollments ?? []);
        $progressByEnrollmentId = collect($progressByEnrollmentId ?? []);
    @endphp

    <section class="flex min-h-full w-full flex-col gap-5 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Percorsi formativi')" />

        @if($enrollments->isEmpty())
            <div class="card border border-dashed border-base-300 bg-base-100 shadow-sm">
                <div class="card-body items-center gap-4 py-14 text-center">
                    <div class="flex size-16 items-center justify-center rounded-full bg-base-200 text-base-content/60">
                        <x-lucide-map class="h-8 w-8" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-base-content">{{ __('Nessun percorso assegnato') }}</h2>
                        <p class="mt-1 text-sm text-base-content/60">{{ __('Non risulti iscritto a percorsi formativi attivi.') }}</p>
                    </div>
                </div>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($enrollments as $enrollment)
                    @php
                        $trainingPath = $enrollment->trainingPath;
                        $progress = $progressByEnrollmentId->get((int) $enrollment->getKey(), [
                            'completed_courses' => 0,
                            'total_courses' => 0,
                            'completion_percentage' => 0,
                        ]);
                    @endphp

                    <article class="card border border-base-300 bg-base-100 shadow-sm">
                        <div class="card-body gap-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-lg font-semibold text-base-content">{{ $trainingPath?->title ?? __('Percorso non disponibile') }}</h2>
                                    @if(filled($trainingPath?->code))
                                        <p class="mt-1 text-xs text-base-content/60">{{ __('Codice') }}: {{ $trainingPath->code }}</p>
                                    @endif
                                </div>
                                <span class="badge badge-outline h-fit">
                                    {{ __(':completed/:total corsi', ['completed' => $progress['completed_courses'], 'total' => $progress['total_courses']]) }}
                                </span>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between gap-2 text-xs text-base-content/70">
                                    <span>{{ __('Avanzamento percorso') }}</span>
                                    <span class="font-semibold text-base-content">{{ $progress['completion_percentage'] }}%</span>
                                </div>
                                <progress class="progress progress-primary h-2 w-full" value="{{ $progress['completion_percentage'] }}" max="100"></progress>
                            </div>

                            <div class="card-actions justify-end">
                                <a href="{{ route('user.training-paths.show', $enrollment) }}" class="btn btn-primary btn-sm">
                                    {{ __('Apri percorso') }}
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.user>
