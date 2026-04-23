<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-6">
        <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold">{{ __('Quiz questions') }}</h2>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('add-question-modal').showModal()">{{ __('Add question') }}</button>
        </div>

        <dialog id="add-question-modal" class="modal">
            <div class="modal-box w-full max-w-xl">
                <h3 class="font-bold text-lg mb-4">{{ __('New question') }}</h3>
                <form method="POST" action="{{ route('admin.courses.modules.quiz.questions.store', [$course, $module]) }}" class="flex flex-col gap-4 items-stretch">
                        @csrf
                        <div class="flex flex-col grow">
                                <label for="question-text-modal" class="label label-text mb-1">{{ __('Question text') }}</label>
                                <textarea id="question-text-modal" name="text" class="textarea textarea-bordered w-full resize-y md:h-[50px] md:min-h-[50px]" placeholder="{{ __('Question text') }}" required></textarea>
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
        @foreach ($module->quizQuestions as $question)
            <div class="border rounded p-4 bg-base-200">
                <div class="mb-6 flex flex-col gap-2 md:flex-row md:items-end">
                    <form method="POST" action="{{ route('admin.courses.modules.quiz.questions.update', [$course, $module, $question]) }}" class="flex flex-col gap-2 grow md:flex-row md:items-end">
                        @csrf
                        @method('PUT')
                        <div class="flex flex-col grow">
                            <label class="label label-text mb-1">{{ __('Question text') }}</label>
                            <textarea name="text" class="textarea textarea-bordered w-full resize-y md:h-[50px] md:min-h-[50px]" required>{{ $question->text }}</textarea>
                        </div>
                        <div class="flex flex-col w-full min-w-[5rem] md:w-20 md:min-w-[5rem]">
                            <label class="label label-text mb-1">{{ __('Points') }}</label>
                            <input type="number" name="points" value="{{ $question->points }}" class="input input-bordered w-full" min="1" required>
                        </div>
                        <div class="flex gap-2 w-full md:w-auto">
                            <button type="submit" class="btn btn-primary w-1/2 md:w-fit">{{ __('Save') }}</button>
                        </div>
                    </form>
                    <div class="flex gap-2 w-full md:w-auto">
                        <form method="POST" action="{{ route('admin.courses.modules.quiz.questions.delete', [$course, $module, $question]) }}" class="js-delete-question-form flex-1 md:flex-none" style="display:inline">
                            @csrf
                            @method('DELETE')
                                <button type="submit" class="btn btn-error w-full md:w-fit">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>
                <div>
                    <div class="flex gap-4 justify-between items-center">
                        <button
                            type="button"
                            class="btn btn-sm btn-primary js-toggle-answers"
                            data-target="answers-{{ $question->id }}"
                            aria-expanded="false"
                            aria-controls="answers-{{ $question->id }}">
                            {{ __('Show answers') }}
                        </button>
                    </div>
                    <div id="answers-{{ $question->id }}" class="flex flex-col gap-4 mt-2" style="display: none;">
                        <div class="flex gap-4 justify-between items-center">
                            <h3 class="text-base font-semibold mb-2">{{ __('Answers') }}</h3>
                            <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('add-answer-modal-{{ $question->id }}').showModal()">{{ __('Add answer') }}</button>
                        </div>
                        <dialog id="add-answer-modal-{{ $question->id }}" class="modal">
                            <div class="modal-box w-full max-w-lg">
                                <h3 class="font-bold text-lg mb-4">{{ __('New answer') }}</h3>
                                <form method="POST" action="{{ route('admin.courses.modules.quiz.answers.store', [$course, $module, $question]) }}" class="flex flex-col gap-2 mb-2">
                                    @csrf
                                    <input type="text" name="text" class="input input-bordered w-full" placeholder="{{ __('Answer text') }}" required>
                                    <div class="flex gap-2 justify-end mt-2">
                                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-answer-modal-{{ $question->id }}').close()">{{ __('Cancel') }}</button>
                                        <button type="submit" class="btn btn-primary">{{ __('Save answer') }}</button>
                                    </div>
                                </form>
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                    <button>{{ __('Close') }}</button>
                            </form>
                        </dialog>
                        @if (!$question->answers->isEmpty())
                            @foreach ($question->answers as $answer)
                                <div>
                                    <form method="POST" action="{{ route('admin.courses.modules.quiz.answers.set-correct', [$course, $module, $question, $answer]) }}" class="flex items-center">
                                        @csrf
                                        <div class="flex gap-6 justify-start">
                                          @if ($question->correct_answer_id === $answer->id)
                                              <div class="mb-2">
                                                <span class="inline-block rounded px-2 py-1 w-18 text-xs font-semibold border border-success bg-success/10 text-success">{{ __('Correct') }}</span>
                                                <button type="submit" class="btn btn-primary btn-xs mr-2" title="Clicca per modificare">{{ __('Change to wrong') }}</button>
                                              </div>
                                          @else
                                              <div class="mb-2">
                                                <span class="inline-block rounded px-2 py-1 w-18 text-xs font-semibold border border-error bg-error/10 text-error">{{ __('Wrong') }}</span>
                                                <button type="submit" class="btn btn-primary btn-xs mr-2" title="Clicca per modificare">{{ __('Change to correct') }}</button>
                                              </div>
                                          @endif
                                        </div>
                                    </form>
                                    <div class="flex flex-col gap-2 mb-2 md:flex-row md:items-end">
                                        <input type="text" name="text" value="{{ $answer->text }}" class="input input-bordered w-full md:w-fit md:grow mb-2 md:mb-0" form="update-answer-{{ $answer->id }}" required>
                                        <div class="flex gap-2 w-full md:w-fit">
                                            <form id="update-answer-{{ $answer->id }}" method="POST" action="{{ route('admin.courses.modules.quiz.answers.update', [$course, $module, $question, $answer]) }}" class="flex-1 w-1/2 md:w-fit">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-primary w-full md:w-fit text-nowrap">{{ __('Edit text') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.courses.modules.quiz.answers.delete', [$course, $module, $question, $answer]) }}" class="js-delete-answer-form flex-1 w-1/2 md:w-fit" style="display:inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-error w-full md:w-fit text-nowrap">{{ __('Delete') }}</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-toggle-answers').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var targetId = btn.getAttribute('data-target');
                    var target = document.getElementById(targetId);
                    var expanded = btn.getAttribute('aria-expanded') === 'true';
                    if (target) {
                        target.style.display = expanded ? 'none' : '';
                        btn.setAttribute('aria-expanded', !expanded);
                        btn.textContent = expanded ? 'Mostra risposte' : 'Nascondi risposte';
                    }
                });
            });

            document.querySelectorAll('.js-delete-question-form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Eliminare la domanda?')) {
                        e.preventDefault();
                    }
                });
            });
            document.querySelectorAll('.js-delete-answer-form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Eliminare la risposta?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</div>
