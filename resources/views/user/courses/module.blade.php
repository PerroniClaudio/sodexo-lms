<x-layouts.app>
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-course-id="{{ $course->id }}"
        data-module-id="{{ $module->id }}"
        data-module-type="{{ $module->type }}"
        data-module-title="{{ $module->title }}"
        data-passing-score="{{ $module->passing_score ?? '' }}"
        data-signed-playback-url="{{ route('user.courses.modules.video.signed-playback', [$course, $module]) }}"
        data-video-progress-url="{{ route('user.courses.modules.video.progress', [$course, $module]) }}"
        data-video-complete-url="{{ route('user.courses.modules.video.complete', [$course, $module]) }}"
        data-quiz-url="{{ route('user.courses.modules.quiz.show', [$course, $module]) }}"
        data-quiz-submit-url="{{ route('user.courses.modules.quiz.submit', [$course, $module]) }}"
        data-next-module-url="{{ $nextModule ? route('user.courses.modules.player', [$course, $nextModule]) : '' }}"
        data-next-module-title="{{ $nextModule->title ?? '' }}"
        data-csrf="{{ csrf_token() }}"
    >
        <x-page-header :title="$module->title">
            <x-slot:actions>
                <a href="{{ route('user.courses.show', $course) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Torna al corso') }}</span>
                </a>
            </x-slot:actions>
            <span class="badge badge-ghost">{{ $course->title }}</span>
        </x-page-header>

        <div id="module-player">
            {{-- Carica il componente corretto in base al tipo di modulo --}}
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

                @case('residential')
                    @include('user.courses.modules.residential')
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

    {{-- Script di inizializzazione del modulo --}}
    @vite('resources/js/modules/module-loader.js')
</x-layouts.app>
