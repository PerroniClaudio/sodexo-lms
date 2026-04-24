<!-- Template domanda quiz -->
<template id="quiz-question-template">
    <div class="mb-6 p-4 border border-base-300 rounded-lg bg-base-200 flex flex-col gap-2" data-question-id>
        <div class="flex items-center gap-2">
            <span class="inline-block rounded px-2 py-1 w-18 text-xs font-semibold border" data-valid-badge></span>
            <span class="text-xs text-error" data-invalid-reason style="display:none"></span>
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
        <div class="mt-2">
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
            <div class="flex gap-2 justify-start">
                <span class="flex items-center rounded px-2 py-1 w-18 text-xs font-semibold border" data-correct-badge></span>
                <button type="button" class="btn btn-primary btn-xs w-[128px] mr-2 js-toggle-correct-btn" data-qid data-aid><span data-toggle-correct-label></span></button>
            </div>
            <input type="text" class="input input-sm text-sm input-bordered w-full md:mb-0" data-answer-text required>
        </div>
        <div class="flex gap-2 w-full md:w-auto">
            <button type="button" class="btn btn-sm btn-primary flex-1 md:w-fit js-save-answer-btn whitespace-nowrap" data-qid data-aid>{{ __('Edit text') }}</button>
            <button type="button" class="btn btn-sm btn-error flex-1 md:w-fit text-nowrap js-delete-answer-btn" data-qid data-aid>{{ __('Delete') }}</button>
        </div>
    </div>
</template>
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="card border border-base-300 bg-base-100 shadow-sm">
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
        <div id="quiz-questions-list" data-api-url="{{ route('admin.api.courses.modules.quiz.questions.index', [$course, $module]) }}">
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
    <script>
    // Recupera il token CSRF dal meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    // Toast temporaneo
    function showToast(msg, type = 'success') {
        let toast = document.createElement('div');
        toast.textContent = msg;
        toast.className = 'fixed z-50 bottom-6 right-6 px-4 py-2 rounded shadow-lg text-white ' + (type === 'success' ? 'bg-green-600' : 'bg-red-600');
        document.body.appendChild(toast);
        setTimeout(() => { toast.remove(); }, 3000);
    }

    // Aggiorna il campo max_score del form principale
    async function updateMaxScoreInput() {
        try {
            const res = await fetch(`{{ route('admin.api.courses.modules.max_score', [$course, $module]) }}`);
            const data = await res.json();
            if (typeof data.max_score !== 'undefined') {
                // Cerca l'input max_score nel form principale
                const maxScoreInput = document.querySelector('input[name="max_score"]');
                if (maxScoreInput) {
                    maxScoreInput.value = data.max_score;
                }
            }
        } catch (e) {
            // Silenzia errori
        }
    }
    function renderQuestions(questions) {
        const container = document.getElementById('quiz-questions-list');
        const questionTemplate = document.getElementById('quiz-question-template');
        const answerTemplate = document.getElementById('quiz-answer-template');
        if (!container || !questionTemplate || !answerTemplate) return;
        container.innerHTML = '';
        questions.forEach(q => {
            const qNode = questionTemplate.content.cloneNode(true);
            // Imposta data-question-id sul nodo radice domanda
            qNode.querySelector('[data-question-id]').setAttribute('data-question-id', q.id);
            // Badge validità domanda
            const validBadge = qNode.querySelector('[data-valid-badge]');
            const invalidReason = qNode.querySelector('[data-invalid-reason]');
            if (q.isValid) {
                validBadge.textContent = "{{ __('Valid') }}";
                validBadge.className = 'inline-block rounded px-2 py-1 w-fit text-xs font-semibold border border-success bg-success/10 text-success whitespace-nowrap';
                invalidReason.style.display = 'none';
            } else {
                validBadge.textContent = "{{ __('Not valid') }}";
                validBadge.className = 'inline-block rounded px-2 py-1 w-fit text-xs font-semibold border border-error bg-error/10 text-error whitespace-nowrap';
                invalidReason.textContent = "{{ __('A quiz question must have 4 answers and one correct answer.') }}";
                invalidReason.style.display = '';
            }
            // Popola input modificabili
            qNode.querySelector('[data-question-text]').value = q.text;
            qNode.querySelector('[data-question-points]').value = q.points;
            qNode.querySelector('.js-save-question-btn').dataset.id = q.id;
            qNode.querySelector('.js-delete-question-btn').dataset.id = q.id;
            qNode.querySelector('.js-add-answer-btn').dataset.id = q.id;
            // Risposte
            const answersList = qNode.querySelector('[data-answers-list]');
            (q.answers || []).forEach(a => {
                const aNode = answerTemplate.content.cloneNode(true);
                // Badge corretto/sbagliato
                const badge = aNode.querySelector('[data-correct-badge]');
                badge.textContent = q.correct_answer_id === a.id ? "{{ __('Correct') }}" : "{{ __('Wrong') }}";
                badge.className = 'flex items-center rounded px-2 py-1 w-fit text-xs font-semibold border ' + (q.correct_answer_id === a.id ? 'border-success bg-success/10 text-success' : 'border-error bg-error/10 text-error') + ' whitespace-nowrap';
                // Bottone toggle correct
                const toggleBtn = aNode.querySelector('.js-toggle-correct-btn');
                toggleBtn.dataset.qid = q.id;
                toggleBtn.dataset.aid = a.id;
                aNode.querySelector('[data-toggle-correct-label]').textContent = q.correct_answer_id === a.id ? "{{ __('Change to wrong') }}" : "{{ __('Change to correct') }}";
                // Testo risposta
                aNode.querySelector('[data-answer-text]').value = a.text;
                // Bottoni azione risposta
                aNode.querySelector('.js-save-answer-btn').dataset.qid = q.id;
                aNode.querySelector('.js-save-answer-btn').dataset.aid = a.id;
                aNode.querySelector('.js-delete-answer-btn').dataset.qid = q.id;
                aNode.querySelector('.js-delete-answer-btn').dataset.aid = a.id;
                answersList.appendChild(aNode);
            });
            container.appendChild(qNode);
        });
    }

    // Carica tutte le domande/risposte via API
    async function loadQuestions() {
        const url = document.getElementById('quiz-questions-list').dataset.apiUrl;
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (data.success) {
                renderQuestions(data.questions);
            } else {
                showToast('Errore nel caricamento domande', 'error');
            }
        } catch (e) {
            showToast('Errore di rete', 'error');
        }
    }

    // Intercetta il submit del form di aggiunta domanda
    document.addEventListener('DOMContentLoaded', function () {

        loadQuestions();

        // Submit domanda: click su "Save" (template)
        document.body.addEventListener('click', function(e) {
            if (e.target.classList.contains('js-save-question-btn')) {
                const qid = e.target.dataset.id;
                // Trova il nodo domanda più vicino
                const questionNode = e.target.closest('[data-question-id]');
                if (!questionNode) return;
                // Recupera valori
                const text = questionNode.querySelector('[data-question-text]')?.value || '';
                const points = questionNode.querySelector('[data-question-points]')?.value || '';
                // Prepara la chiamata API
                const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/${qid}`;
                const formData = new FormData();
                formData.append('text', text);
                formData.append('points', points);
                formData.append('_method', 'PUT');
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        showToast(data.message || 'Domanda aggiornata');
                        loadQuestions();
                        updateMaxScoreInput();
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                }).catch(() => showToast('Errore di rete', 'error'));
            }
        });

        // Submit risposta: click su "Edit text" (template)
        document.body.addEventListener('click', function(e) {
            if (e.target.classList.contains('js-save-answer-btn')) {
                const qid = e.target.dataset.qid;
                const aid = e.target.dataset.aid;
                // Trova il nodo risposta più vicino
                const answerNode = e.target.closest('[data-id]');
                if (!answerNode) return;
                const text = answerNode.querySelector('[data-answer-text]')?.value || '';
                const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/${qid}/answers/${aid}`;
                const formData = new FormData();
                formData.append('text', text);
                fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        showToast(data.message || 'Risposta aggiornata');
                        loadQuestions();
                        updateMaxScoreInput();
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                }).catch(() => showToast('Errore di rete', 'error'));
            }
        });
        document.getElementById('add-question-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = e.target;
            const url = form.action;
            const formData = new FormData(form);
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Domanda aggiunta');
                    form.reset();
                    document.getElementById('add-question-modal').close();
                    await loadQuestions();
                    updateMaxScoreInput();
                } else {
                    showToast(data.message || 'Errore', 'error');
                }
            } catch (err) {
                showToast('Errore di rete', 'error');
            }
        });
        // Intercetta update domanda
        document.body.addEventListener('submit', async function(e) {
            if (e.target.classList.contains('js-update-question-form')) {
                e.preventDefault();
                const form = e.target;
                const qid = form.dataset.id;
                const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/${qid}`;
                const formData = new FormData(form);
                try {
                    const res = await fetch(url, {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message || 'Domanda aggiornata');
                        await loadQuestions();
                        updateMaxScoreInput();
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                } catch (err) {
                    showToast('Errore di rete', 'error');
                }
            }
        });

        // Elimina domanda (template)
        document.body.addEventListener('click', function(e) {
            if (e.target.classList.contains('js-delete-question-btn')) {
                if (!confirm('Eliminare la domanda?')) return;
                const qid = e.target.dataset.id;
                const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/${qid}`;
                fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        showToast(data.message || 'Domanda eliminata');
                        loadQuestions();
                        updateMaxScoreInput();
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                }).catch(() => showToast('Errore di rete', 'error'));
            }
        });

        // Intercetta new answer

        // Mostra il modal custom per aggiunta risposta
        document.body.addEventListener('click', function(e) {
            if (e.target.classList.contains('js-add-answer-btn')) {
                const qid = e.target.dataset.id;
                document.getElementById('add-answer-question-id').value = qid;
                document.getElementById('answer-text-modal').value = '';
                document.getElementById('add-answer-modal').showModal();
            }
        });

        // Gestisce il submit del form di aggiunta risposta
        document.getElementById('add-answer-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const qid = document.getElementById('add-answer-question-id').value;
            const text = document.getElementById('answer-text-modal').value;
            if (!text) return;
            const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/` + encodeURIComponent(qid) + `/answers`;
            const formData = new FormData();
            formData.append('text', text);
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Risposta aggiunta');
                    document.getElementById('add-answer-modal').close();
                    await loadQuestions();
                } else {
                    showToast(data.message || 'Errore', 'error');
                }
            } catch (err) {
                showToast('Errore di rete', 'error');
            }
        });

        // Intercetta update answer
        document.body.addEventListener('submit', async function(e) {
            if (e.target.classList.contains('js-update-answer-form')) {
                e.preventDefault();
                const form = e.target;
                const qid = form.dataset.qid;
                const aid = form.dataset.aid;
                const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/${qid}/answers/${aid}`;
                const formData = new FormData(form);
                try {
                    const res = await fetch(url, {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message || 'Risposta aggiornata');
                        await loadQuestions();
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                } catch (err) {
                    showToast('Errore di rete', 'error');
                }
            }
        });

        // Elimina risposta (template)
        document.body.addEventListener('click', function(e) {
            if (e.target.classList.contains('js-delete-answer-btn')) {
                if (!confirm('Eliminare la risposta?')) return;
                const qid = e.target.dataset.qid;
                const aid = e.target.dataset.aid;
                const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/${qid}/answers/${aid}`;
                fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        showToast(data.message || 'Risposta eliminata');
                        loadQuestions();
                        updateMaxScoreInput();
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                }).catch(() => showToast('Errore di rete', 'error'));
            }
        });

        // Imposta risposta corretta (template)
        document.body.addEventListener('click', function(e) {
            let btn = e.target;
            // Se clicco su uno span dentro il bottone, risalgo
            if (btn && btn.classList && !btn.classList.contains('js-toggle-correct-btn')) {
                btn = btn.closest('.js-toggle-correct-btn');
            }
            if (btn && btn.classList && btn.classList.contains('js-toggle-correct-btn')) {
                const qid = btn.dataset.qid;
                const aid = btn.dataset.aid;
                const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/${qid}/answers/${aid}/set-correct`;
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        showToast(data.message || 'Risposta corretta aggiornata');
                        loadQuestions();
                        updateMaxScoreInput();
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                }).catch(() => showToast('Errore di rete', 'error'));
            }
        });
    });
    </script>
</div>
