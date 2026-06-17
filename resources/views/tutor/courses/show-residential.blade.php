<x-layouts.user>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:gap-8 sm:p-6 lg:p-8">
        <x-page-header :title="$course->title">
            <x-slot:actions>
                <a href="{{ route('tutor.courses.index') }}" class="btn btn-ghost">
                    {{ __('Torna ai corsi') }}
                </a>
            </x-slot:actions>

            {{ $course->description }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                @if ($course->cover_image_path)
                    <div class="overflow-hidden rounded-box border border-base-300">
                        <img
                            src="{{ route('tutor.courses.cover-image.show', $course) }}"
                            alt="{{ __('Copertina del corso :title', ['title' => $course->title]) }}"
                            class="h-auto max-h-[28rem] w-full object-cover"
                            loading="lazy"
                        >
                    </div>
                @endif

                <h2 class="text-2xl font-semibold text-base-content">{{ __('Informazioni sul corso') }}</h2>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-box border border-base-300 bg-base-200/50 p-4">
                        <dt class="flex items-center gap-2 text-sm font-medium text-base-content/60">
                            <x-lucide-calendar-clock class="h-4 w-4" />
                            {{ __('Inizio') }}
                        </dt>
                        <dd class="mt-2 text-lg font-semibold text-base-content">
                            {{ $firstResidentialStartAt?->format('d/m/Y H:i') ?? __('Non disponibile') }}
                        </dd>
                    </div>

                    <div class="rounded-box border border-base-300 bg-base-200/50 p-4">
                        <dt class="flex items-center gap-2 text-sm font-medium text-base-content/60">
                            <x-lucide-map-pin class="h-4 w-4" />
                            {{ __('Sede') }}
                        </dt>
                        <dd class="mt-2 text-lg font-semibold text-base-content">
                            {{ $course->venue?->address ?? __('Non disponibile') }}
                        </dd>
                    </div>
                </dl>

                @if ($course->categories->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach ($course->categories as $courseCategory)
                            <span class="badge badge-outline badge-primary h-fit">{{ $courseCategory->name }}</span>
                        @endforeach
                    </div>
                @endif

                <p class="max-w-4xl text-base leading-8 text-base-content/80">
                    {{ $course->description }}
                </p>

                @if ($course->poster_pdf_path)
                    <div class="pt-2">
                        <a href="{{ route('tutor.courses.poster-pdf.download', $course) }}" class="btn btn-outline btn-primary">
                            <x-lucide-download class="h-4 w-4" />
                            {{ __('Scarica locandina') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <section class="space-y-4">
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-2">
                        <h2 class="text-2xl font-semibold text-base-content">{{ __('Gestione presenze') }}</h2>
                        <p class="text-base text-base-content/70">
                            {{ __('Apri il registro presenze per segnare entrata e uscita degli utenti iscritti al corso.') }}
                        </p>
                    </div>

                    <a href="{{ route('tutor.courses.attendance.index', $course) }}" class="btn btn-primary gap-2">
                        <x-lucide-clipboard-signature class="h-4 w-4" />
                        {{ __('Registra presenze utenti') }}
                    </a>
                </div>
            </div>
        </section>
    </div>
</x-layouts.user>