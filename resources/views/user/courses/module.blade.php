@php
    $moduleTypeMeta = [
        'video' => [
            'label' => __('Video'),
            'icon' => 'lucide-clapperboard',
            'badge' => 'badge-primary',
        ],
        'res' => [
            'label' => __('Sessione in aula'),
            'icon' => 'lucide-users',
            'badge' => 'badge-accent',
        ],
        'live' => [
            'label' => __('Live'),
            'icon' => 'lucide-monitor-play',
            'badge' => 'badge-secondary',
        ],
        'scorm' => [
            'label' => __('SCORM'),
            'icon' => 'lucide-package',
            'badge' => 'badge-info',
        ],
        'learning_quiz' => [
            'label' => __('Quiz'),
            'icon' => 'lucide-badge-help',
            'badge' => 'badge-error',
        ],
        'satisfaction_quiz' => [
            'label' => __('Gradimento'),
            'icon' => 'lucide-message-square-heart',
            'badge' => 'badge-success',
        ],
    ];

    $currentModuleMeta = $moduleTypeMeta[$module->type] ?? [
        'label' => strtoupper((string) $module->type),
        'icon' => 'lucide-shapes',
        'badge' => 'badge-ghost',
    ];
@endphp

<x-layouts.course-player
    :course="$course"
    :modules="$modules"
    :current-module="$module"
    :enrollment="$enrollment"
    :module-type-meta="$moduleTypeMeta"
>
    <x-slot:headerActions>
        <a href="{{ route('user.courses.show', $course) }}" class="btn btn-outline">
            <x-lucide-arrow-left class="h-4 w-4" />
            {{ __('Torna al corso') }}
        </a>
    </x-slot:headerActions>

    <div
        class="space-y-6"
        data-course-id="{{ $course->id }}"
        data-module-id="{{ $module->id }}"
        data-module-type="{{ $module->type }}"
        data-module-title="{{ $module->title }}"
        data-passing-score="{{ $module->passing_score ?? '' }}"
        data-signed-playback-url="{{ route('user.courses.modules.video.signed-playback', [$course, $module]) }}"
        data-video-tracking-url="{{ route('user.courses.modules.video.tracking', [$course, $module]) }}"
        data-video-events-url="{{ route('user.courses.modules.video.events', [$course, $module]) }}"
        data-video-progress-url="{{ route('user.courses.modules.video.progress', [$course, $module]) }}"
        data-video-complete-url="{{ route('user.courses.modules.video.complete', [$course, $module]) }}"
        data-scorm-packages-url="{{ route('user.courses.modules.scorm.packages.index', [$course, $module]) }}"
        data-quiz-url="{{ $module->isSatisfactionQuiz() ? route('user.courses.modules.satisfaction-survey.show', [$course, $module]) : route('user.courses.modules.quiz.show', [$course, $module]) }}"
        data-quiz-submit-url="{{ $module->isSatisfactionQuiz() ? route('user.courses.modules.satisfaction-survey.submit', [$course, $module]) : route('user.courses.modules.quiz.submit', [$course, $module]) }}"
        data-next-module-url="{{ $nextModule ? route('user.courses.modules.player', [$course, $nextModule]) : '' }}"
        data-next-module-title="{{ $nextModule->title ?? '' }}"
        data-csrf="{{ csrf_token() }}"
    >
        <div class="card border border-base-300 bg-base-200/40 shadow-sm">
            <div class="card-body p-4 sm:p-5 lg:p-6">
                <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-base-content/45">{{ __('Contenuto modulo') }}</p>

                <div id="module-player">
                    @switch($module->type)
                        @case('video')
                            @include('user.courses.modules.video')
                            @break

                        @case('learning_quiz')
                            @include('user.courses.modules.learning-quiz')
                            @break

                        @case('satisfaction_quiz')
                            @include('user.courses.modules.satisfaction-quiz')
                            @break

                        @case('live')
                            @include('user.courses.modules.live')
                            @break

                        @case('scorm')
                            @include('user.courses.modules.scorm')
                            @break

                        @case('res')
                            @include('user.courses.modules.res')
                            @break

                        @default
                            <div class="card border border-base-300 bg-base-100 shadow-sm">
                                <div class="card-body">
                                    <p class="text-base-content/70">{{ __('Gestione :type da implementare', ['type' => $module->type]) }}</p>
                                </div>
                            </div>
                    @endswitch
                </div>
            </div>
        </div>

        <div class="card border border-base-300 bg-base-200/40 shadow-sm">
            <div class="card-body">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-base-content/45">{{ __('Descrizione modulo') }}</p>
                <p class="mt-1 text-sm leading-7 text-base-content/75 sm:text-base">
                    {{ $module->description ?: __('Nessuna descrizione disponibile per questo modulo.') }}
                </p>
            </div>
        </div>
    </div>

    @vite('resources/js/modules/module-loader.js')
</x-layouts.course-player>
