<x-layouts.app>
    <section class="flex min-h-screen items-center justify-center bg-base-200 px-4 py-10">
        <div class="card w-full max-w-2xl rounded-box border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4 p-8">
                <span class="badge badge-primary badge-outline w-fit">{{ __('Diretta non ancora disponibile') }}</span>

                <div>
                    <h1 class="text-3xl font-semibold text-base-content">
                        {{ $module->title }}
                    </h1>

                    <p class="mt-3 text-base-content/70">
                        {{ $waitingMessage ?: __('La diretta comincia all\'orario stabilito. Potrai accedere alla live a partire da :datetime.', ['datetime' => $module->appointment_start_time?->format('d/m/Y H:i')]) }}
                    </p>
                </div>

                <div class="rounded-box border border-base-300 bg-base-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">
                        {{ __('Corso') }}
                    </p>
                    <p class="mt-2 text-base font-medium text-base-content">
                        {{ $course?->title ?? __('Corso non disponibile') }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
