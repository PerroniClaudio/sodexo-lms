<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-6">
        <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold">Domande del Quiz</h2>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('add-question-modal').showModal()">Aggiungi domanda</button>
        </div>

        <dialog id="add-question-modal" class="modal">
            <div class="modal-box w-full max-w-xl">
                <h3 class="font-bold text-lg mb-4">Nuova domanda</h3>
                <form method="POST" action="{{ route('admin.courses.modules.quiz.questions.store', [$course, $module]) }}" class="flex flex-col gap-4 items-stretch">
                        @csrf
                        <div class="flex flex-col grow">
                                <label for="question-text-modal" class="label label-text mb-1">Testo domanda</label>
                                <textarea id="question-text-modal" name="text" class="textarea textarea-bordered w-full resize-y md:h-[50px] md:min-h-[50px]" placeholder="Testo domanda" required></textarea>
                        </div>
                        <div class="flex flex-col w-full">
                                <label for="question-points-modal" class="label label-text mb-1">Punti</label>
                                <input id="question-points-modal" type="number" name="points" class="input input-bordered w-full" placeholder="Punti" min="1" value="1" required>
                        </div>
                        <div class="flex gap-2 justify-end">
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-question-modal').close()">Annulla</button>
                            <button type="submit" class="btn btn-primary">Salva domanda</button>
                        </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>Chiudi</button>
            </form>
        </dialog>
        @foreach ($module->quizQuestions as $question)
            <div class="border rounded p-4 bg-base-200">
                <form method="POST" action="{{ route('admin.courses.modules.quiz.questions.update', [$course, $module, $question]) }}" class="flex flex-col gap-2 mb-6 md:flex-row md:items-end">
                    @csrf
                    @method('PUT')
                    <div class="flex flex-col grow">
                        <label class="label label-text mb-1">Testo domanda</label>
                        <textarea name="text" class="textarea textarea-bordered w-full resize-y md:h-[50px] md:min-h-[50px]" required>{{ $question->text }}</textarea>
                    </div>
                    <div class="flex flex-col w-full min-w-[5rem] md:w-20 md:min-w-[5rem]">
                        <label class="label label-text mb-1">Punti</label>
                        <input type="number" name="points" value="{{ $question->points }}" class="input input-bordered w-full" min="1" required>
                    </div>
                    <div class="flex gap-2 w-full md:w-auto">
                        <button type="submit" class="btn btn-primary w-1/2 md:w-fit">Salva</button>
                        <form method="POST" action="{{ route('admin.courses.modules.quiz.questions.delete', [$course, $module, $question]) }}" class="js-delete-question-form" style="display:inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-error w-1/2 md:w-fit">Elimina</button>
                        </form>
                    </div>
                </form>
                <div>
                    <div class="flex gap-4 justify-between items-center">
                        <button
                            type="button"
                            class="btn btn-sm btn-primary js-toggle-answers"
                            data-target="answers-{{ $question->id }}"
                            aria-expanded="false"
                            aria-controls="answers-{{ $question->id }}">
                            Mostra risposte
                        </button>
                    </div>
                    <div id="answers-{{ $question->id }}" class="flex flex-col gap-4 mt-2" style="display: none;">
                        <div class="flex gap-4 justify-between items-center">
                            <h3 class="text-base font-semibold mb-2">Risposte</h3>
                            <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('add-answer-modal-{{ $question->id }}').showModal()">Aggiungi risposta</button>
                        </div>
                        <dialog id="add-answer-modal-{{ $question->id }}" class="modal">
                            <div class="modal-box w-full max-w-lg">
                                <h3 class="font-bold text-lg mb-4">Nuova risposta</h3>
                                <form method="POST" action="{{ route('admin.courses.modules.quiz.answers.store', [$course, $module, $question]) }}" class="flex flex-col gap-2 mb-2">
                                    @csrf
                                    <input type="text" name="text" class="input input-bordered w-full" placeholder="Testo risposta" required>
                                    <div class="flex gap-2 justify-end mt-2">
                                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-answer-modal-{{ $question->id }}').close()">Annulla</button>
                                        <button type="submit" class="btn btn-primary">Salva risposta</button>
                                    </div>
                                </form>
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button>Chiudi</button>
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
                                                <span class="inline-block rounded px-2 py-1 w-18 text-xs font-semibold border border-success bg-success/10 text-success">Corretta</span>
                                                <button type="submit" class="btn btn-primary btn-xs mr-2" title="Clicca per modificare">Modifica in sbagliata</button>
                                              </div>
                                          @else
                                              <div class="mb-2">
                                                <span class="inline-block rounded px-2 py-1 w-18 text-xs font-semibold border border-error bg-error/10 text-error">Sbagliata</span>
                                                <button type="submit" class="btn btn-primary btn-xs mr-2" title="Clicca per modificare">Modifica in corretta</button>
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
                                                <button type="submit" class="btn btn-primary w-full md:w-fit text-nowrap">Modifica testo</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.courses.modules.quiz.answers.delete', [$course, $module, $question, $answer]) }}" class="js-delete-answer-form flex-1 w-1/2 md:w-fit" style="display:inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-error w-full md:w-fit text-nowrap">Elimina</button>
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
