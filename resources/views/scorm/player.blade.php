<x-layouts.course-player
    :course="$course"
    :modules="$modules"
    :current-module="$module"
    :enrollment="$enrollment"
    :module-type-meta="$moduleTypeMeta"
>
    @vite('resources/js/scorm-player.js')

    <x-slot:headerActions>
        <a href="{{ route('user.courses.show', $course) }}" class="btn btn-outline">
            <x-lucide-arrow-left class="h-4 w-4" />
            {{ __('Torna al corso') }}
        </a>
    </x-slot:headerActions>

    <section
        data-scorm-player-root
        data-scorm-sco-identifier="{{ $launchSco['sco_identifier'] }}"
        data-scorm-entry-point="{{ $launchSco['entry_point'] }}"
    >
        <script type="application/json" data-scorm-player-config>@json($scormPlayerConfig)</script>

        <div class="space-y-6">
            <div class="rounded-box border border-base-300 bg-base-100 shadow-sm">
                <div class="border-b border-base-300 px-5 py-4">
                    <h1 class="text-lg font-semibold text-base-content">SCORM Player</h1>
                </div>

                <iframe
                    src="{{ $scormPlayerConfig['entryPointUrl'] }}"
                    title="{{ __('SCORM content player') }}"
                    class="h-[78vh] w-full border-0"
                    allowfullscreen
                    data-scorm-player-iframe
                ></iframe>

                <div class="border-t border-base-300 px-5 py-3 text-sm text-base-content/70" data-scorm-player-status>
                    {{ __('Stiamo preparando il contenuto...') }}
                </div>
            </div>
        </div>
    </section>
</x-layouts.course-player>
