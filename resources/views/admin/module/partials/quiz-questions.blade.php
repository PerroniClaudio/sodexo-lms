<div class="card border border-base-300 bg-base-100 shadow-sm">
    {{-- Inclusione JS standalone per gestione domande quiz --}}
    @vite('resources/js/admin-quiz-questions.js')

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="card-body gap-6">
        <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold">{{ __('Quiz questions') }}</h2>
                {{-- <button type="button" class="btn btn-primary" onclick="document.getElementById('add-question-modal').showModal()">{{ __('Add question') }}</button> --}}
                <button type="button" class="btn btn-primary" onclick="document.getElementById('add-question-modal').showModal()">
                        <span>{{ __('New question') }}</span>
                        <x-lucide-plus class="h-4 w-4" />
                </button>
        </div>

        <dialog id="add-question-modal" class="modal">
            <div class="modal-box w-full max-w-xl">
                <h3 class="font-bold text-lg mb-4">{{ __('New question') }}</h3>
                <form id="add-question-form" method="POST" action="{{ route('admin.api.courses.modules.quiz.questions.store', [$course, $module]) }}" class="flex flex-col gap-4 items-stretch">
                    @csrf
                    <div class="flex flex-col grow">
                        <label for="question-text-modal" class="label label-text mb-1">{{ __('Question text') }}</label>
                        <textarea id="question-text-modal" name="text" class="textarea textarea-bordered w-full resize-y md:h-12.5 md:min-h-12.5" placeholder="{{ __('Question text') }}" required></textarea>
                    </div>
                    <div class="flex flex-col w-full">
                        <label for="question-points-modal" class="label label-text mb-1">{{ __('Points') }}</label>
                        <input id="question-points-modal" type="number" name="points" class="input input-bordered w-full" placeholder="{{ __('Points') }}" min="1" value="1" required>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-question-modal').close()">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Save question') }}</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>{{ __('Close') }}</button>
            </form>
        </dialog>
        <div id="quiz-questions-list"
            data-api-url="{{ route('admin.api.courses.modules.quiz.questions.index', [$course, $module]) }}"
            data-base-url="{{ url('admin/api/courses') }}"
            data-course-id="{{ $course->id }}"
            data-module-id="{{ $module->id }}"
            data-max-score-url="{{ route('admin.api.courses.modules.max_score', [$course, $module]) }}"
            data-question-store-url="{{ route('admin.api.courses.modules.quiz.questions.store', [$course, $module]) }}"
        >
            {{-- Il contenuto verrà renderizzato dinamicamente --}}
        </div>

        <!-- Modal per aggiunta risposta -->
        <dialog id="add-answer-modal" class="modal">
            <div class="modal-box w-full max-w-md">
                <h3 class="font-bold text-lg mb-4">{{ __('New answer') }}</h3>
                <form id="add-answer-form" class="flex flex-col gap-4 items-stretch">
                    <input type="hidden" name="question_id" id="add-answer-question-id">
                    <div class="flex flex-col grow">
                        <label for="answer-text-modal" class="label label-text mb-1">{{ __('Answer text') }}</label>
                        <input id="answer-text-modal" name="text" class="input input-bordered w-full" placeholder="{{ __('Answer text') }}" required>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-answer-modal').close()">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Save answer') }}</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>{{ __('Close') }}</button>
            </form>
        </dialog>

    </div>

    
    <!-- Template domanda quiz -->
    <template id="quiz-question-template">
        <div class="mb-6 p-4 border border-base-300 rounded-lg bg-base-200 flex flex-col gap-2" data-question-id>
            <div class="flex items-center gap-2">
                <span class="badge badge-sm badge-success whitespace-nowrap" data-valid-badge-valid hidden>{{ __('Valid') }}</span>
                <span class="badge badge-sm badge-error whitespace-nowrap" data-valid-badge-invalid hidden>{{ __('Not valid') }}</span>
                <span class="text-xs text-error" data-invalid-reason-empty style="display:none"></span>
                <span class="text-xs text-error" data-invalid-reason-answers style="display:none">{{ __('A quiz question must have 4 answers and one correct answer.') }}</span>
            </div>
            <div class="flex flex-col md:flex-row md:items-end gap-2 mb-2">
                <div class="flex-1 flex flex-col">
                    <label class="label label-text mb-1">{{ __('Question text') }}</label>
                    <textarea class="textarea textarea-bordered w-full resize-y md:h-12.5 md:min-h-12.5" data-question-text required></textarea>
                </div>
                <div class="flex flex-col w-full min-w-20 md:w-20 md:min-w-20">
                    <label class="label label-text mb-1">{{ __('Points') }}</label>
                    <input type="number" class="input input-bordered w-full" min="1" data-question-points required />
                </div>
                <div class="flex gap-2 w-full md:w-auto mt-2 md:mt-0">
                    <button type="button" class="btn btn-primary flex-1 md:w-fit js-save-question-btn" data-id>{{ __('Save') }}</button>
                    <button type="button" class="btn btn-error flex-1 md:w-fit js-delete-question-btn" data-id>{{ __('Delete') }}</button>
                </div>
            </div>
            <div class=" border-t border-primary-600 pt-2">
                <div class="flex gap-4 justify-between items-center">
                    <h3 class="text-base font-semibold mb-2">{{ __('Answers') }}</h3>
                    <button type="button" class="btn btn-sm btn-primary js-add-answer-btn" data-id>{{ __('New answer') }}</button>
                </div>
                <div class="answers-list flex flex-col gap-6" data-answers-list></div>
            </div>
        </div>
    </template>

    <!-- Template risposta quiz -->
    <template id="quiz-answer-template">
        <div class="flex flex-col gap-2 md:flex-row md:items-end" data-id>
            <div class="flex-1 flex flex-col gap-2">
                <div class="flex gap-2 justify-start items-center">
                    <button type="button" class="btn btn-primary btn-xs w-32 mr-2 js-toggle-correct-btn" data-qid data-aid>
                        <span data-toggle-correct-label-correct hidden>{{ __('Change to wrong') }}</span>
                        <span data-toggle-correct-label-wrong hidden>{{ __('Change to correct') }}</span>
                    </button>
                    <span class="badge badge-sm badge-success whitespace-nowrap" data-correct-badge-correct hidden>{{ __('Correct') }}</span>
                    <span class="badge badge-sm badge-error whitespace-nowrap" data-correct-badge-wrong hidden>{{ __('Wrong') }}</span>
                </div>
                <input type="text" class="input input-sm text-sm input-bordered w-full md:mb-0" data-answer-text required>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <button type="button" class="btn btn-sm btn-primary flex-1 md:w-fit js-save-answer-btn whitespace-nowrap" data-qid data-aid>{{ __('Edit text') }}</button>
                <button type="button" class="btn btn-sm btn-error flex-1 md:w-fit text-nowrap js-delete-answer-btn" data-qid data-aid>{{ __('Delete') }}</button>
            </div>
        </div>
    </template>
</div>
