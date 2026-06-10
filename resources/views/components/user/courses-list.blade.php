@props(['courses'])

@php
    $themeByType = [
        'fad' => [
            'dot' => 'bg-info',
            'type_badge' => 'badge-info badge-soft',
            'type_badge_classes' => 'border-sky-300 text-sky-700',
            'progress' => 'progress-info',
            'button' => 'btn-info',
        ],
        'res' => [
            'dot' => 'bg-secondary',
            'type_badge' => 'badge-secondary badge-soft',
            'type_badge_classes' => '',
            'progress' => 'progress-secondary',
            'button' => 'btn-secondary',
        ],
        'blended' => [
            'dot' => 'bg-warning',
            'type_badge' => 'badge-warning badge-soft',
            'type_badge_classes' => '',
            'progress' => 'progress-warning',
            'button' => 'btn-warning',
        ],
        'fsc' => [
            'dot' => 'bg-secondary',
            'type_badge' => 'badge-secondary badge-soft',
            'type_badge_classes' => '',
            'progress' => 'progress-secondary',
            'button' => 'btn-secondary',
        ],
        'async' => [
            'dot' => 'bg-primary',
            'type_badge' => 'badge-primary badge-soft',
            'type_badge_classes' => '',
            'progress' => 'progress-primary',
            'button' => 'btn-primary',
        ],
        'unknown' => [
            'dot' => 'bg-neutral',
            'type_badge' => 'badge-neutral badge-soft',
            'type_badge_classes' => '',
            'progress' => 'progress-neutral',
            'button' => 'btn-neutral',
        ],
    ];

    $statusLabels = [
        'assigned' => __('Assegnato'),
        'in_progress' => __('In corso'),
        'completed' => __('Completato'),
        'expired' => __('Scaduto'),
        'cancelled' => __('Annullato'),
    ];

    $statusBadges = [
        'assigned' => 'badge-ghost',
        'in_progress' => 'badge-error',
        'completed' => 'badge-success',
        'expired' => 'badge-warning',
        'cancelled' => 'badge-neutral',
    ];

    $statusGroups = [
        'assigned' => 'in_progress',
        'in_progress' => 'in_progress',
        'completed' => 'completed',
        'expired' => 'other',
        'cancelled' => 'other',
    ];
@endphp

<section class="card bg-base-100 border border-base-300 shadow-sm" data-courses-list-root>
    <div class="card-body gap-4 p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h2 class="card-title">
                    <x-lucide-book-marked class="h-5 w-5" />
                    {{ __('I miei corsi') }}
                </h2>
            </div>

            <div role="tablist" class="tabs tabs-box tabs-xs bg-base-200" data-courses-tabs>
                <button type="button" role="tab" class="tab tab-active" data-courses-tab="all">{{ __('Tutti') }}</button>
                <button type="button" role="tab" class="tab" data-courses-tab="in_progress">{{ __('In corso') }}</button>
                <button type="button" role="tab" class="tab" data-courses-tab="completed">{{ __('Completati') }}</button>
            </div>
        </div>

        <div class="min-h-[18rem]">
            @if($courses->isEmpty())
                <div class="flex h-[18rem] items-center justify-center rounded-box border border-dashed border-base-300 px-4 text-center text-sm text-base-content/60" data-courses-empty>
                    {{ __('Nessun corso aperto di recente.') }}
                </div>
            @else
                <div class="grid  gap-3 overflow-y-auto pr-1" data-courses-items>
                    @foreach($courses as $course)
                        @php($theme = $themeByType[$course['type']] ?? $themeByType['unknown'])
                        @php($statusGroup = $statusGroups[$course['status']] ?? 'other')

                        <article
                            class="rounded-box border border-base-300 bg-base-100 p-4"
                            data-course-item
                            data-course-status="{{ $course['status'] }}"
                            data-course-group="{{ $statusGroup }}"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex min-w-0 flex-1 gap-3">
                                    <div class="mt-1 h-3 w-3 shrink-0 rounded-full {{ $theme['dot'] }}"></div>

                                    <div class="min-w-0 flex-1">
                                        <div class="mb-2 flex flex-wrap items-center gap-2">
                                            <span class="badge badge-xs {{ $statusBadges[$course['status']] ?? 'badge-ghost' }} h-fit">
                                                {{ $statusLabels[$course['status']] ?? ucfirst(str_replace('_', ' ', $course['status'])) }}
                                            </span>
                                            <span class="badge badge-xs {{ $theme['type_badge'] }} {{ $theme['type_badge_classes'] }}">
                                                {{ $course['type_label'] }}
                                            </span>
                                        </div>

                                        <h3 class="truncate text-sm font-semibold text-base-content">{{ $course['title'] }}</h3>

                                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-base-content/60">
                                            <span>{{ trans_choice(':count moduli', $course['modules_count'], ['count' => $course['modules_count']]) }}</span>
                                            @if($course['last_accessed_at'])
                                                <span>{{ __('Ultimo accesso') }}: {{ $course['last_accessed_at'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <a
                                    href="{{ $course['open_url'] }}"
                                    class="btn btn-circle btn-sm btn-outline shrink-0 {{ $theme['button'] }}"
                                    aria-label="{{ __('Apri corso :title', ['title' => $course['title']]) }}"
                                >
                                    <x-lucide-play class="h-3.5 w-3.5" />
                                </a>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-3 text-xs text-base-content/60">
                                <span>{{ __('Avanzamento') }}</span>
                                <span class="font-semibold text-base-content">{{ $course['progress'] }}%</span>
                            </div>

                            <progress class="progress mt-2 h-2 w-full {{ $theme['progress'] }}" value="{{ max(0, min(100, $course['progress'])) }}" max="100"></progress>
                        </article>
                    @endforeach
                </div>

                <div class="hidden h-[18rem] items-center justify-center rounded-box border border-dashed border-base-300 px-4 text-center text-sm text-base-content/60 flex" data-courses-filter-empty>
                    {{ __('Nessun corso disponibile per questo filtro.') }}
                </div>
            @endif
        </div>
    </div>
</section>

@once
    @vite('resources/js/components/user-courses-list.js')
@endonce
