@props([
    'events',
    'showAllEventsButton' => true,
])

@php
    $themes = [
        'fad' => ['icon_wrap' => 'bg-info text-info-content', 'course_text' => 'text-info', 'event_icon' => 'monitor-play', 'course_icon' => 'monitor-play'],
        'async' => ['icon_wrap' => 'bg-primary text-primary-content', 'course_text' => 'text-primary', 'event_icon' => 'monitor-play', 'course_icon' => 'monitor-play'],
        'res' => ['icon_wrap' => 'bg-secondary text-secondary-content', 'course_text' => 'text-secondary', 'event_icon' => 'map-pin', 'course_icon' => 'map-pin'],
        'blended' => ['icon_wrap' => 'bg-warning text-warning-content', 'course_text' => 'text-warning', 'event_icon' => 'layers', 'course_icon' => 'layers'],
        'fsc' => ['icon_wrap' => 'bg-secondary text-secondary-content', 'course_text' => 'text-secondary', 'event_icon' => 'briefcase', 'course_icon' => 'briefcase'],
        'unknown' => ['icon_wrap' => 'bg-neutral text-neutral-content', 'course_text' => 'text-neutral', 'event_icon' => 'calendar', 'course_icon' => 'calendar'],
    ];

    $eventTypeIcons = [
        'live' => 'monitor-play',
        'async' => 'monitor-play',
        'res' => 'map-pin',
        'unknown' => 'calendar',
    ];

    $events = collect($events ?? []);
    $dashboardRouteParams = request()->boolean('test') ? ['test' => 1] : [];
    $resolvedAllEventsUrl = $showAllEventsButton ? route('teacher.events', $dashboardRouteParams) : null;
@endphp

<section class="card h-full w-full bg-base-100 border border-base-300 shadow-sm">
    <div class="card-body h-full gap-5">
        <h2 class="card-title"><x-lucide-calendar class="w-6 h-6" /> {{ __('Prossimi Eventi') }}</h2>

        @if ($events->isEmpty())
            <div class="hero min-h-64 rounded-box border border-dashed border-base-300 bg-base-200/40">
                <div class="hero-content text-center text-sm text-base-content/60">
                    {{ __('Nessun evento imminente.') }}
                </div>
            </div>
        @else
            <div class="flex-1 space-y-4 overflow-y-auto pb-2">
                @foreach ($events as $event)
                    @php
                        $theme = $themes[$event['course_type']] ?? $themes[$event['type']] ?? $themes['unknown'];
                        $icon = $eventTypeIcons[$event['type']] ?? $theme['course_icon'];
                        $typeLabel = match ($event['course_type']) {
                            'res' => __('RES'),
                            'async' => __('FAD Asincrona'),
                            'fad' => __('FAD Asincrona'),
                            'blended' => __('Blended'),
                            'fsc' => __('FSC'),
                            default => strtoupper((string) $event['course_type']),
                        };
                    @endphp

                    <div tabindex="0" class="collapse collapse-arrow border border-base-200 bg-base-100 shadow-sm">
                        <div class="collapse-title min-h-0 px-5 py-4">
                            <div class="flex items-center gap-4 pr-6">
                                <div class="avatar placeholder shrink-0">
                                    <div class="{{ $theme['icon_wrap'] }} flex h-14 w-14 items-center justify-center rounded-3xl">
                                        @switch($icon)
                                            @case('video')
                                                <x-lucide-video class="h-4 w-4" />
                                                @break
                                            @case('book-open')
                                                <x-lucide-book-open class="h-4 w-4" />
                                                @break
                                            @case('map-pin')
                                                <x-lucide-map-pin class="h-4 w-4" />
                                                @break
                                            @case('monitor-play')
                                                <x-lucide-monitor-play class="h-4 w-4" />
                                                @break
                                            @case('layers')
                                                <x-lucide-layers class="h-4 w-4" />
                                                @break
                                            @case('briefcase')
                                                <x-lucide-briefcase class="h-4 w-4" />
                                                @break
                                            @default
                                                <x-lucide-calendar class="h-4 w-4" />
                                        @endswitch
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="truncate text-lg font-semibold text-base-content">{{ $event['title'] }}</h3>

                                        @if ($event['is_today'])
                                            <span class="badge badge-info badge-soft badge-sm h-fit">{{ __('Oggi') }}</span>
                                        @endif
                                    </div>

                                    <p class="mt-1 text-sm text-base-content/60">{{ $event['date_label'] }} • {{ $event['time_label'] }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="collapse-content px-5 pb-5 pt-0">
                            <div class="pl-[4.5rem]">
                                <p class="text-sm font-medium {{ $theme['course_text'] }}">{{ $event['course_title'] }}</p>
                                <p class="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-base-content/50">{{ $typeLabel }}</p>

                                @if ($event['class_name'])
                                    <p class="mt-1 text-[11px] uppercase tracking-[0.18em] text-base-content/40">{{ $event['class_name'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($resolvedAllEventsUrl !== null)
                <div class="card-actions mt-auto">
                    <a href="{{ $resolvedAllEventsUrl }}" class="btn btn-outline btn-block">
                    {{ __('Vedi tutti gli eventi') }}
                    </a>
                </div>
            @endif
        @endif
    </div>
</section>
