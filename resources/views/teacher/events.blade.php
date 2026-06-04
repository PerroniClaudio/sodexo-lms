<x-layouts.user>
    <section class="flex min-h-full w-full flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold text-base-content">{{ __('Tutti gli eventi') }}</h1>
            <p class="text-sm text-base-content/70">{{ __('Panoramica completa dei prossimi appuntamenti docente.') }}</p>
        </div>

        <x-teacher.next-events :events="$events" :show-all-events-button="false" />
    </section>
</x-layouts.user>
