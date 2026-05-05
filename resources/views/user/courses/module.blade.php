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

            {{-- Template: modulo video --}}
            @if($module->type === 'video')
                <script type="module" src="https://unpkg.com/@mux/mux-player@latest/dist/mux-player.js"></script>
                <template id="tpl-video">
                    <div class="card border border-base-300 bg-base-100 shadow-sm">
                        <div class="card-body gap-4">
                            <div id="video-loading" class="flex items-center justify-center py-12">
                                <span class="loading loading-spinner loading-lg"></span>
                            </div>
                            <div id="video-player-wrapper" class="hidden">
                                <div data-mux-player-container></div>
                            </div>
                            <div id="video-error" class="hidden text-error text-sm">
                                {{ __('Impossibile caricare il video. Riprova più tardi.') }}
                            </div>
                            <div id="video-completed-msg" class="hidden">
                                <div class="alert alert-success">
                                    <x-lucide-check-circle class="h-5 w-5" />
                                    <span>{{ __('Modulo completato!') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            @endif

            {{-- Template: modulo quiz apprendimento --}}
            @if($module->type === 'learning_quiz')
                <template id="tpl-quiz">
                    <div class="card border border-base-300 bg-base-100 shadow-sm">
                        <div class="card-body gap-6">
                            <div id="quiz-loading" class="flex items-center justify-center py-12">
                                <span class="loading loading-spinner loading-lg"></span>
                            </div>
                            <div id="quiz-content" class="hidden">
                                <form id="quiz-form" class="flex flex-col gap-6">
                                    <div id="quiz-questions"></div>
                                    <div class="flex justify-end">
                                        <button type="submit" id="quiz-submit-btn" class="btn btn-primary">
                                            {{ __('Invia risposte') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div id="quiz-result" class="hidden"></div>
                        </div>
                    </div>
                </template>
            @endif

            {{-- Template: modulo quiz gradimento --}}
            @if($module->type === 'satisfaction_quiz')
                <template id="tpl-quiz">
                    <div class="card border border-base-300 bg-base-100 shadow-sm">
                        <div class="card-body gap-6">
                            <div id="quiz-loading" class="flex items-center justify-center py-12">
                                <span class="loading loading-spinner loading-lg"></span>
                            </div>
                            <div id="quiz-content" class="hidden">
                                <form id="quiz-form" class="flex flex-col gap-6">
                                    <div id="quiz-questions"></div>
                                    <div class="flex justify-end">
                                        <button type="submit" id="quiz-submit-btn" class="btn btn-primary">
                                            {{ __('Invia risposte') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div id="quiz-result" class="hidden"></div>
                        </div>
                    </div>
                </template>
            @endif

            {{-- Placeholder per tipi non ancora gestiti --}}
            @if(!in_array($module->type, ['video', 'learning_quiz', 'satisfaction_quiz']))
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body">
                        <p class="text-base-content/70">{{ __('Gestione :type da implementare', ['type' => $module->type]) }}</p>
                    </div>
                </div>
            @endif

        </div>
    </div>

    @vite('resources/js/user-module-player.js')
</x-layouts.app>
