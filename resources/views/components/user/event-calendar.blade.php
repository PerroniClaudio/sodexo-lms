@props([
    'eventsUrl' => request()->boolean('test') ? route('user.dashboard.calendar-events.fake') : route('user.dashboard.calendar-events'),
])

<div class="user-event-calendar card w-full bg-base-100 border border-base-300 card-sm shadow-sm">
    <div class="card-body flex flex-col gap-4">
        <h2 class="card-title order-1"><x-lucide-calendar class="w-6 h-6" /> {{ __('Calendario') }}</h2>
        <div
            id="user-event-calendar"
            class="order-3 xl:order-2"
            data-events-url="{{ $eventsUrl }}"
            data-empty-label="{{ __('Nessun evento in questa giornata.') }}"
            data-error-label="{{ __('Impossibile caricare gli eventi del calendario.') }}"
            data-list-title-singular="{{ __(':count evento il :date') }}"
            data-list-title-plural="{{ __(':count eventi il :date') }}"
            data-type-live="{{ __('FAD Asincrona') }}"
            data-type-res="{{ __('RES') }}"
        ></div>
        <div id="user-event-calendar-day-events" class="order-2 hidden rounded-box bg-base-100 p-4 sm:p-5 xl:order-3">
            <p id="user-event-calendar-day-events-title" class="text-sm font-semibold text-base-content/70"></p>
            <div id="user-event-calendar-day-events-list" class="mt-3 space-y-3"></div>
        </div>
    </div>
</div>

@once
    @vite('resources/js/components/user-event-calendar.js')
@endonce
