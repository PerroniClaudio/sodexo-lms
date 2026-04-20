<x-layouts.admin>
    <section class="mx-auto max-w-5xl px-6 py-16">
        <div class="rounded-3xl border border-base-300 bg-base-200 p-8 shadow-sm">
            <span class="badge badge-neutral badge-outline mb-4">{{ __('Admin') }}</span>
            <h1 class="text-3xl font-semibold">{{ $module->title }}</h1>
            <p class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">
                {{ __('Corso') }}
            </p>
            <p class="mt-2 text-base font-medium">
                {{ $course?->title ?? __('Corso non disponibile') }}
            </p>
            <p class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">
                {{ __('Programmazione') }}
            </p>
            <p class="mt-2 text-base font-medium">
                {{ $module->appointment_start_time?->format('d/m/Y H:i') }}
                @if ($module->appointment_end_time !== null)
                    {{ __('-') }} {{ $module->appointment_end_time->format('H:i') }}
                @endif
            </p>
            <p class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">
                {{ __('Descrizione') }}
            </p>
            <p class="mt-3 text-base-content/70">
                {{ $module->description ?: __('Nessuna descrizione disponibile per questo modulo live.') }}
            </p>
        </div>
    </section>
</x-layouts.admin>
