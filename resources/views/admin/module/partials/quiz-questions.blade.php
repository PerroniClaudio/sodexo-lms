<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-6">
        <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold">{{ __('Quiz questions') }}</h2>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('add-question-modal').showModal()">{{ __('Add question') }}</button>
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
                <h3 class="font-bold text-lg mb-4">{{ __('Add answer') }}</h3>
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

    // Funzione per renderizzare la lista domande/risposte con Blade-like HTML (con form, bottoni, traduzioni)
    function renderQuestions(questions) {
        const container = document.getElementById('quiz-questions-list');
        if (!container) return;
        container.innerHTML = '';
        questions.forEach(q => {
            // Domanda
            let html = `<div id="anchor-question-${q.id}" class="border rounded p-4 bg-base-200 mb-4" data-id="${q.id}">
                <div class="mb-6 flex flex-col gap-2 md:flex-row md:items-end">
                    <form class="flex flex-col gap-2 grow md:flex-row md:items-end js-update-question-form" data-id="${q.id}">
                        <div class="flex flex-col grow">
                            <label class="label label-text mb-1">${window.trans['Question text'] || 'Question text'}</label>
                            <textarea name="text" class="textarea textarea-bordered w-full resize-y md:h-12.5 md:min-h-12.5" required>${q.text}</textarea>
                        </div>
                        <div class="flex flex-col w-full min-w-20 md:w-20 md:min-w-20">
                            <label class="label label-text mb-1">${window.trans['Points'] || 'Points'}</label>
                            <input type="number" name="points" value="${q.points}" class="input input-bordered w-full" min="1" required>
                        </div>
                    </form>
                    <div class="flex gap-2 w-full md:w-auto mt-2 md:mt-0">
                        <button type="button" class="btn btn-primary w-1/2 md:w-fit js-save-question-btn" data-id="${q.id}">${window.trans['Save'] || 'Save'}</button>
                        <form class="js-delete-question-form flex-1 md:flex-none" data-id="${q.id}" style="display:inline">
                            <button type="button" class="btn btn-error w-full md:w-fit">${window.trans['Delete'] || 'Delete'}</button>
                        </form>
                    </div>
                </div>

                <div>
                    <div id="answers-${q.id}" class="flex flex-col gap-4 mt-2">
                        <div class="flex gap-4 justify-between items-center">
                            <h3 class="text-base font-semibold mb-2">${window.trans['Answers'] || 'Answers'}</h3>
                            <button type="button" class="btn btn-sm btn-primary js-add-answer-btn" data-id="${q.id}">${window.trans['Add answer'] || 'Add answer'}</button>
                        </div>
                        <div class="answers-list flex flex-col gap-6">
                            ${(q.answers || []).map(a => `
                                <div class="flex flex-col gap-2 md:flex-row md:items-end" data-id="${a.id}">
                                    <div class="flex-1 flex flex-col gap-2">
                                        <form class="flex items-center js-set-correct-form" data-qid="${q.id}" data-aid="${a.id}">
                                            <div class="flex gap-2 justify-start">
                                                <span class="inline-block rounded px-2 py-1 w-18 text-xs font-semibold border ${q.correct_answer_id === a.id ? 'border-success bg-success/10 text-success' : 'border-error bg-error/10 text-error'}">
                                                    ${q.correct_answer_id === a.id ? (window.trans['Correct'] || 'Correct') : (window.trans['Wrong'] || 'Wrong')}
                                                </span>
                                                <button type="submit" class="btn btn-primary btn-xs w-[128px] mr-2">${q.correct_answer_id === a.id ? (window.trans['Change to wrong'] || 'Change to wrong') : (window.trans['Change to correct'] || 'Change to correct')}</button>
                                            </div>
                                        </form>
                                        <form class="js-update-answer-form" data-qid="${q.id}" data-aid="${a.id}">
                                            <input type="text" name="text" value="${a.text}" class="input input-bordered w-full md:mb-0" required>
                                        </form>
                                    </div>
                                    <div class="flex gap-2 w-full md:w-auto">
                                        <button type="button" class="btn btn-primary w-1/2 md:w-fit js-save-answer-btn" data-qid="${q.id}" data-aid="${a.id}">${window.trans['Edit text'] || 'Edit text'}</button>
                                        <form class="js-delete-answer-form flex-1 w-1/2 md:w-fit" data-qid="${q.id}" data-aid="${a.id}" style="display:inline">
                                            <button type="button" class="btn btn-error w-full md:w-fit text-nowrap">${window.trans['Delete'] || 'Delete'}</button>
                                        </form>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>`;
            container.insertAdjacentHTML('beforeend', html);
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
        // Carica traduzioni JS (solo chiavi usate)
        window.trans = {
            'Question text': "{{ __('Question text') }}",
            'Points': "{{ __('Points') }}",
            'Save': "{{ __('Save') }}",
            'Delete': "{{ __('Delete') }}",
            'Answers': "{{ __('Answers') }}",
            'Add answer': "{{ __('Add answer') }}",
            'Correct': "{{ __('Correct') }}",
            'Wrong': "{{ __('Wrong') }}",
            'Change to wrong': "{{ __('Change to wrong') }}",
            'Change to correct': "{{ __('Change to correct') }}",
            'Edit text': "{{ __('Edit text') }}"
        };

        loadQuestions();

        // Intercetta submit aggiunta domanda
                        // Submit risposta: click su "Edit text" fuori dal form
                        document.body.addEventListener('click', function(e) {
                            if (e.target.classList.contains('js-save-answer-btn')) {
                                const qid = e.target.dataset.qid;
                                const aid = e.target.dataset.aid;
                                const form = document.querySelector(`form.js-update-answer-form[data-qid='${qid}'][data-aid='${aid}']`);
                                if (form) form.requestSubmit();
                            }
                        });
                // Submit domanda: click su "Save" fuori dal form
                document.body.addEventListener('click', function(e) {
                    if (e.target.classList.contains('js-save-question-btn')) {
                        const qid = e.target.dataset.id;
                        const form = document.querySelector(`form.js-update-question-form[data-id='${qid}']`);
                        if (form) form.requestSubmit();
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
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                } catch (err) {
                    showToast('Errore di rete', 'error');
                }
            }
        });

        // Intercetta delete domanda
        document.body.addEventListener('click', async function(e) {
            if (e.target.closest('.js-delete-question-form')) {
                e.preventDefault();
                if (!confirm('Eliminare la domanda?')) return;
                const form = e.target.closest('.js-delete-question-form');
                const qid = form.dataset.id;
                const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/${qid}`;
                try {
                    const res = await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message || 'Domanda eliminata');
                        await loadQuestions();
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                } catch (err) {
                    showToast('Errore di rete', 'error');
                }
            }
        });

        // Intercetta add answer

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

        // Intercetta delete answer
        document.body.addEventListener('click', async function(e) {
            if (e.target.closest('.js-delete-answer-form')) {
                e.preventDefault();
                if (!confirm('Eliminare la risposta?')) return;
                const form = e.target.closest('.js-delete-answer-form');
                const qid = form.dataset.qid;
                const aid = form.dataset.aid;
                const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/${qid}/answers/${aid}`;
                try {
                    const res = await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message || 'Risposta eliminata');
                        await loadQuestions();
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                } catch (err) {
                    showToast('Errore di rete', 'error');
                }
            }
        });

        // Intercetta set correct answer
        document.body.addEventListener('submit', async function(e) {
            if (e.target.classList.contains('js-set-correct-form')) {
                e.preventDefault();
                const form = e.target;
                const qid = form.dataset.qid;
                const aid = form.dataset.aid;
                const url = `{{ url('admin/api/courses') }}/${encodeURIComponent({{ $course->id }})}/modules/${encodeURIComponent({{ $module->id }})}/quiz/questions/${qid}/answers/${aid}/set-correct`;
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message || 'Risposta corretta aggiornata');
                        await loadQuestions();
                    } else {
                        showToast(data.message || 'Errore', 'error');
                    }
                } catch (err) {
                    showToast('Errore di rete', 'error');
                }
            }
        });
    });
    </script>
</div>
