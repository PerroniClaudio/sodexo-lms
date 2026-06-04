@php
    $calendarLegendItems = [
        ['label' => __('RES'), 'color' => '--calendar-course-type-res'],
        ['label' => __('FAD Asincrona'), 'color' => '--calendar-course-type-async'],
    ];
@endphp

<div class="admin-event-calendar user-event-calendar card h-full w-full border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body h-full">
        <h2 class="card-title"><x-lucide-calendar class="h-6 w-6" /> {{ __('Calendario attività') }}</h2>
        <div
            id="admin-event-calendar"
            data-events-url="{{ route('admin.dashboard.calendar-events') }}"
            data-empty-label="{{ __('Nessun evento in questa giornata.') }}"
            data-error-label="{{ __('Impossibile caricare gli eventi del calendario.') }}"
            data-list-title-singular="{{ __(':count evento il :date') }}"
            data-list-title-plural="{{ __(':count eventi il :date') }}"
            data-type-res="{{ __('RES') }}"
            data-type-async="{{ __('FAD Asincrona') }}"
        ></div>
        <div id="admin-event-calendar-day-events" class="hidden rounded-box bg-base-100 p-4 sm:p-5">
            <p id="admin-event-calendar-day-events-title" class="text-sm font-semibold text-base-content/70"></p>
            <div id="admin-event-calendar-day-events-list" class="mt-3 space-y-3"></div>
        </div>
        <div class="rounded-box border border-base-200 p-4 shadow-sm sm:p-5">
            <p class="text-sm font-semibold text-base-content/70">{{ __('Legenda colori') }}</p>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($calendarLegendItems as $item)
                    <div class="flex items-center gap-3 rounded-box bg-white px-3 py-2">
                        <span
                            class="inline-block h-3 w-3 shrink-0 rounded-full"
                            style="background-color: var({{ $item['color'] }});"
                            aria-hidden="true"
                        ></span>
                        <span class="text-sm text-base-content/80">{{ $item['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@once
    @vite('resources/js/components/admin-event-calendar.js')
@endonce
