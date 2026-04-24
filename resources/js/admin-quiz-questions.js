
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
        const quizList = document.getElementById('quiz-questions-list');
        const maxScoreUrl = quizList.dataset.maxScoreUrl;
        const res = await fetch(maxScoreUrl);
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
        // Badge validità domanda (mostra/nasconde solo, classi già precaricate)
        const validBadge = qNode.querySelector('[data-valid-badge-valid]');
        const invalidBadge = qNode.querySelector('[data-valid-badge-invalid]');
        const invalidReasonEmpty = qNode.querySelector('[data-invalid-reason-empty]');
        const invalidReasonAnswers = qNode.querySelector('[data-invalid-reason-answers]');
        if (q.isValid) {
            validBadge.hidden = false;
            invalidBadge.hidden = true;
            invalidReasonEmpty.style.display = '';
            invalidReasonAnswers.style.display = 'none';
        } else {
            validBadge.hidden = true;
            invalidBadge.hidden = false;
            invalidReasonEmpty.style.display = 'none';
            invalidReasonAnswers.style.display = '';
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
            // Badge corretto/sbagliato (mostra/nasconde solo, classi già precaricate)
            const badgeCorrect = aNode.querySelector('[data-correct-badge-correct]');
            const badgeWrong = aNode.querySelector('[data-correct-badge-wrong]');
            if (q.correct_answer_id === a.id) {
                badgeCorrect.hidden = false;
                // badgeWrong.hidden = true;
            } else {
                badgeCorrect.hidden = true;
                // badgeWrong.hidden = false;
            }
            // Bottone toggle correct
            const toggleBtn = aNode.querySelector('.js-toggle-correct-btn');
            toggleBtn.dataset.qid = q.id;
            toggleBtn.dataset.aid = a.id;
            // Mostra/nasconde la label corretta
            const labelCorrect = toggleBtn.querySelector('[data-toggle-correct-label-correct]');
            const labelWrong = toggleBtn.querySelector('[data-toggle-correct-label-wrong]');
            if (q.correct_answer_id === a.id) {
                labelCorrect.hidden = false;
                labelWrong.hidden = true;
            } else {
                labelCorrect.hidden = true;
                labelWrong.hidden = false;
            }
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
            const quizList = document.getElementById('quiz-questions-list');
            const baseUrl = quizList.dataset.baseUrl;
            const courseId = quizList.dataset.courseId;
            const moduleId = quizList.dataset.moduleId;
            const url = `${baseUrl}/${encodeURIComponent(courseId)}/modules/${encodeURIComponent(moduleId)}/quiz/questions/${qid}`;
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
            const quizList = document.getElementById('quiz-questions-list');
            const baseUrl = quizList.dataset.baseUrl;
            const courseId = quizList.dataset.courseId;
            const moduleId = quizList.dataset.moduleId;
            const url = `${baseUrl}/${encodeURIComponent(courseId)}/modules/${encodeURIComponent(moduleId)}/quiz/questions/${qid}/answers/${aid}`;
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
            const quizList = document.getElementById('quiz-questions-list');
            const baseUrl = quizList.dataset.baseUrl;
            const courseId = quizList.dataset.courseId;
            const moduleId = quizList.dataset.moduleId;
            const url = `${baseUrl}/${encodeURIComponent(courseId)}/modules/${encodeURIComponent(moduleId)}/quiz/questions/${qid}`;
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
            const quizList = document.getElementById('quiz-questions-list');
            const baseUrl = quizList.dataset.baseUrl;
            const courseId = quizList.dataset.courseId;
            const moduleId = quizList.dataset.moduleId;
            const url = `${baseUrl}/${encodeURIComponent(courseId)}/modules/${encodeURIComponent(moduleId)}/quiz/questions/${qid}`;
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
            const quizList = document.getElementById('quiz-questions-list');
            const baseUrl = quizList.dataset.baseUrl;
            const courseId = quizList.dataset.courseId;
            const moduleId = quizList.dataset.moduleId;
            const url = `${baseUrl}/${encodeURIComponent(courseId)}/modules/${encodeURIComponent(moduleId)}/quiz/questions/${encodeURIComponent(qid)}/answers`;
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
            const quizList = document.getElementById('quiz-questions-list');
            const baseUrl = quizList.dataset.baseUrl;
            const courseId = quizList.dataset.courseId;
            const moduleId = quizList.dataset.moduleId;
            const url = `${baseUrl}/${encodeURIComponent(courseId)}/modules/${encodeURIComponent(moduleId)}/quiz/questions/${qid}/answers/${aid}`;
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
            const quizList = document.getElementById('quiz-questions-list');
            const baseUrl = quizList.dataset.baseUrl;
            const courseId = quizList.dataset.courseId;
            const moduleId = quizList.dataset.moduleId;
            const url = `${baseUrl}/${encodeURIComponent(courseId)}/modules/${encodeURIComponent(moduleId)}/quiz/questions/${qid}/answers/${aid}`;
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
            const quizList = document.getElementById('quiz-questions-list');
            const baseUrl = quizList.dataset.baseUrl;
            const courseId = quizList.dataset.courseId;
            const moduleId = quizList.dataset.moduleId;
            const url = `${baseUrl}/${encodeURIComponent(courseId)}/modules/${encodeURIComponent(moduleId)}/quiz/questions/${qid}/answers/${aid}/set-correct`;
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