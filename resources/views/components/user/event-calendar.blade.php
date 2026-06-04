<div class="user-event-calendar card w-full bg-base-100 card-sm shadow-sm">
    <div class="card-body">
        <h2 class="card-title"><x-lucide-calendar class="w-6 h-6" /> {{ __('Calendario') }}</h2>
        <div
            id="user-event-calendar"
            data-events-url="{{ request()->boolean('test') ? route('user.dashboard.calendar-events.fake') : route('user.dashboard.calendar-events') }}"
            data-empty-label="{{ __('Nessun evento in questa giornata.') }}"
            data-error-label="{{ __('Impossibile caricare gli eventi del calendario.') }}"
            data-list-title-singular="{{ __(':count evento il :date') }}"
            data-list-title-plural="{{ __(':count eventi il :date') }}"
            data-type-live="{{ __('FAD Asincrona') }}"
            data-type-res="{{ __('RES') }}"
        ></div>
        <div id="user-event-calendar-day-events" class="hidden rounded-box bg-base-100 p-4 sm:p-5">
            <p id="user-event-calendar-day-events-title" class="text-sm font-semibold text-base-content/70"></p>
            <div id="user-event-calendar-day-events-list" class="mt-3 space-y-3"></div>
        </div>
    </div>
</div>

@once
    @vite('resources/js/components/user-event-calendar.js')
@endonce
