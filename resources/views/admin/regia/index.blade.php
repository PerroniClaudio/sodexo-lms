<x-layouts.admin>
    <section class="mx-auto max-w-6xl px-6 py-10">
        <div class="mb-8">
            <span class="badge badge-neutral badge-outline">{{ __('Admin') }}</span>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('Regia') }}</h1>
            <p class="mt-2 text-sm text-base-content/70">
                {{ __('Live programmate per oggi gestite tramite player MUX.') }}
            </p>
        </div>

        @if ($modules->isEmpty())
            <div class="rounded-3xl border border-base-300 bg-base-100 p-8 shadow-sm">
                <p class="text-base-content/70">{{ __('Nessuna live di regia programmata per oggi.') }}</p>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($modules as $module)
                    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-semibold">{{ $module->title }}</h2>
                                <p class="mt-2 text-sm text-base-content/70">{{ $module->course?->title ?? __('Corso non disponibile') }}</p>
                            </div>

                            <span class="badge badge-outline">
                                {{ $module->appointment_start_time?->format('H:i') ?? __('Orario n/d') }}
                            </span>
                        </div>

                        <p class="mt-4 text-sm text-base-content/70">
                            {{ $module->description ?: __('Nessuna descrizione disponibile.') }}
                        </p>

                        <div class="mt-6 flex items-center justify-between gap-3">
                            <div class="text-xs uppercase tracking-[0.2em] text-base-content/45">
                                {{ $module->appointment_start_time?->format('d/m/Y H:i') }}
                            </div>

                            <a href="{{ route('admin.regia.show', $module) }}" class="btn btn-primary">
                                {{ __('Apri regia') }}
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.admin>
