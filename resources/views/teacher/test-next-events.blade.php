<x-layouts.user>
    <section class="mx-auto flex min-h-full w-full max-w-3xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold text-base-content">{{ __('Preview card Prossimi Eventi') }}</h1>
            <p class="text-sm text-base-content/70">{{ __('Route di test con dati simulati per il componente teacher.') }}</p>
        </div>

        <x-teacher.next-events :events="$nextEvents" />
    </section>
</x-layouts.user>
