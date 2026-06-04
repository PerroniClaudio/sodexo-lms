<div class="teacher-event-calendar user-event-calendar card h-full w-full bg-base-100 card-sm shadow-sm">
    <div class="card-body h-full">
        <h2 class="card-title"><x-lucide-calendar class="w-6 h-6" /> {{ __('Calendario') }}</h2>
        <div
            id="teacher-event-calendar"
            data-events-url="{{ request()->boolean('test') ? route('teacher.dashboard.calendar-events.fake') : route('teacher.dashboard.calendar-events') }}"
            data-empty-label="{{ __('Nessun evento in questa giornata.') }}"
            data-error-label="{{ __('Impossibile caricare gli eventi del calendario.') }}"
            data-list-title-singular="{{ __(':count evento il :date') }}"
            data-list-title-plural="{{ __(':count eventi il :date') }}"
            data-type-res="{{ __('RES') }}"
            data-type-async="{{ __('FAD Asincrona') }}"
        ></div>
        <div id="teacher-event-calendar-day-events" class="hidden rounded-box bg-base-100 p-4 sm:p-5">
            <p id="teacher-event-calendar-day-events-title" class="text-sm font-semibold text-base-content/70"></p>
            <div id="teacher-event-calendar-day-events-list" class="mt-3 space-y-3"></div>
        </div>
    </div>
</div>

@once
    @vite('resources/js/components/teacher-event-calendar.js')
@endonce
