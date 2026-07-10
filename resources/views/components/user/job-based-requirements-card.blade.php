@props(['courseEnrollments', 'requirements'])

<section class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-4 p-4 sm:p-5">
        <div class="flex items-start gap-3">
            <x-lucide-shield-alert class="mt-0.5 h-5 w-5 text-warning" />
            <div>
                <h2 class="card-title">{{ __('Requisiti da completare') }}</h2>
                <p class="text-sm text-base-content/70">{{ __('Completa i corsi indicati per ottenere gli attestati richiesti dal tuo ruolo e dalle tue mansioni.') }}</p>
            </div>
        </div>

        @if ($requirements->isEmpty())
            <div class="rounded-box border border-dashed border-success/40 bg-success/5 p-4 text-sm text-success">
                {{ __('Non hai requisiti ruolo/mansione da completare.') }}
            </div>
        @else
            <div class="grid gap-3">
                @foreach ($requirements as $requirement)
                    <article class="rounded-box border border-warning/30 bg-warning/5 p-4">
                        <h3 class="font-semibold text-base-content">{{ $requirement->name }}</h3>
                        @if ($requirement->description)
                            <p class="mt-1 text-sm text-base-content/70">{{ $requirement->description }}</p>
                        @endif

                        <div class="mt-3 grid gap-2">
                            @forelse ($requirement->courses as $course)
                                @php($enrollment = $courseEnrollments->get($course->getKey()))
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-box bg-base-100 px-3 py-2 text-sm">
                                    <span class="font-medium">{{ $course->title }}</span>
                                    @if ($enrollment)
                                        <a href="{{ route('user.courses.show', $course) }}" class="btn btn-primary btn-xs">
                                            {{ $enrollment->status === 'completed' ? __('Verifica attestato') : __('Apri corso') }}
                                        </a>
                                    @else
                                        <span class="badge badge-outline">{{ __('Da assegnare') }}</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-base-content/70">{{ __('Nessun corso è ancora associato a questo requisito. Contatta il tuo amministratore.') }}</p>
                            @endforelse
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
