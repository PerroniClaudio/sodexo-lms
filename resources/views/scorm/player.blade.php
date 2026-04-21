<x-layouts.app>
    @vite('resources/js/scorm-player.js')

    <section class="min-h-screen bg-base-100" data-scorm-player-root>
        <script type="application/json" data-scorm-player-config>@json($scormPlayerConfig)</script>

        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div class="rounded-box border border-base-300 bg-base-100 p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">{{ __('SCORM Player') }}</p>
                        <h1 class="mt-2 text-2xl font-semibold">{{ $module->title }}</h1>
                        <p class="mt-2 text-sm text-base-content/70">
                            {{ __('Course: :course', ['course' => $course->title]) }}
                        </p>
                    </div>

                    <div class="grid gap-2 text-sm text-base-content/70">
                        <p><span class="font-medium text-base-content">{{ __('Version') }}:</span> {{ strtoupper($package->version ?? 'n/a') }}</p>
                        <p><span class="font-medium text-base-content">{{ __('Entry point') }}:</span> {{ $package->entry_point }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-box border border-base-300 bg-base-100 shadow-sm">
                <div class="border-b border-base-300 px-5 py-3 text-sm text-base-content/70" data-scorm-player-status>
                    {{ __('Connessione runtime in inizializzazione...') }}
                </div>

                <iframe
                    src="{{ route('user.courses.modules.scorm.asset', [$course, $module, $package, 'path' => $package->entry_point]) }}"
                    title="{{ __('SCORM content player') }}"
                    class="h-[78vh] w-full rounded-b-box border-0"
                    allowfullscreen
                    data-scorm-player-iframe
                ></iframe>
            </div>
        </div>
    </section>
</x-layouts.app>
