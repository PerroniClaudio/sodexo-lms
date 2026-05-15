// Aggiorna la visibilità dei badge validità quiz
async function updateQuizValidityBadge() {
    try {
        const quizList = document.getElementById('quiz-questions-list');
        if (!quizList) return;
        const baseUrl = quizList.dataset.baseUrl;
        const courseId = quizList.dataset.courseId;
        const moduleId = quizList.dataset.moduleId;
        const validityUrl = `${baseUrl}/${encodeURIComponent(courseId)}/modules/${encodeURIComponent(moduleId)}/quiz/validity`;
        const res = await fetch(validityUrl);
        const data = await res.json();
        const badgeContainer = document.getElementById('quiz-validity-badge');
        if (!badgeContainer) return;
        const validBadge = badgeContainer.querySelector('[data-valid-badge]');
        const invalidBadge = badgeContainer.querySelector('[data-invalid-badge]');
        const invalidReason = badgeContainer.querySelector('[data-invalid-reason]');
        if (data.is_valid_quiz) {
            if (validBadge) validBadge.style.display = 'inline-flex';
            if (invalidBadge) invalidBadge.style.display = 'none';
            if (invalidReason) invalidReason.style.display = 'none';
        } else {
            if (validBadge) validBadge.style.display = 'none';
            if (invalidBadge) invalidBadge.style.display = 'inline-flex';
            if (invalidReason) invalidReason.style.display = 'inline';
        }
    } catch (e) {
        // Silenzia errori
    }
}

// Recupera il token CSRF dal meta tag
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
function isQuizEditable() {
    const quizList = document.getElementById('quiz-questions-list');
    return quizList?.dataset.quizEditable === 'true';
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

        if (!isQuizEditable()) {
            const addQuestionTrigger = document.querySelector('button[onclick*="add-question-modal"]');
            const addQuestionModal = document.getElementById('add-question-modal');
            const addQuestionForm = document.getElementById('add-question-form');
            const addAnswerForm = document.getElementById('add-answer-form');

            addQuestionTrigger?.setAttribute('disabled', 'disabled');
            addQuestionForm?.querySelectorAll('input, textarea, button').forEach((element) => element.setAttribute('disabled', 'disabled'));
            addAnswerForm?.querySelectorAll('input, button').forEach((element) => element.setAttribute('disabled', 'disabled'));
            addQuestionModal?.setAttribute('data-locked', 'true');

            qNode.querySelector('[data-question-text]').setAttribute('disabled', 'disabled');
            qNode.querySelector('[data-question-points]').setAttribute('disabled', 'disabled');
            qNode.querySelector('.js-save-question-btn')?.setAttribute('disabled', 'disabled');
            qNode.querySelector('.js-delete-question-btn')?.setAttribute('disabled', 'disabled');
            qNode.querySelector('.js-add-answer-btn')?.setAttribute('disabled', 'disabled');
            qNode.querySelectorAll('[data-answer-text]').forEach((input) => input.setAttribute('disabled', 'disabled'));
            qNode.querySelectorAll('.js-save-answer-btn, .js-delete-answer-btn, .js-toggle-correct-btn').forEach((button) => button.setAttribute('disabled', 'disabled'));
        }

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
            window.showFlash('error', 'Errore nel caricamento domande');
        }
    } catch (e) {
        window.showFlash('error', 'Errore di rete');
    }
}

// Intercetta il submit del form di aggiunta domanda
document.addEventListener('DOMContentLoaded', function () {

    loadQuestions();

    if (!isQuizEditable()) {
        return;
    }

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
                    window.showFlash('success', data.message || 'Domanda aggiornata');
                    loadQuestions();
                    updateMaxScoreInput();
                        updateQuizValidityBadge();
                } else {
                    window.showFlash('error', data.message || 'Errore');
                }
            }).catch(() => window.showFlash('error', 'Errore di rete'));
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
                    window.showFlash('success', data.message || 'Risposta aggiornata');
                    loadQuestions();
                    updateMaxScoreInput();
                        updateQuizValidityBadge();
                } else {
                    window.showFlash('error', data.message || 'Errore');
                }
            }).catch(() => window.showFlash('error', 'Errore di rete'));
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
                window.showFlash('success', data.message || 'Domanda aggiunta');
                form.reset();
                document.getElementById('add-question-modal').close();
                await loadQuestions();
                updateMaxScoreInput();
                    updateQuizValidityBadge();
            } else {
                window.showFlash('error', data.message || 'Errore');
            }
        } catch (err) {
            window.showFlash('error', 'Errore di rete');
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
                    window.showFlash('success', data.message || 'Domanda aggiornata');
                    await loadQuestions();
                    updateMaxScoreInput();
                        updateQuizValidityBadge();
                } else {
                    window.showFlash('error', data.message || 'Errore');
                }
            } catch (err) {
                window.showFlash('error', 'Errore di rete');
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
                    window.showFlash('success', data.message || 'Domanda eliminata');
                    loadQuestions();
                    updateMaxScoreInput();
                        updateQuizValidityBadge();
                } else {
                    window.showFlash('error', data.message || 'Errore');
                }
            }).catch(() => window.showFlash('error', 'Errore di rete'));
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
                window.showFlash('success', data.message || 'Risposta aggiunta');
                document.getElementById('add-answer-modal').close();
                await loadQuestions();
                updateMaxScoreInput();
                updateQuizValidityBadge();
            } else {
                window.showFlash('error', data.message || 'Errore');
            }
        } catch (err) {
            window.showFlash('error', 'Errore di rete');
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
                    window.showFlash('success', data.message || 'Risposta aggiornata');
                    await loadQuestions();
                    updateMaxScoreInput();
                    updateQuizValidityBadge();
                } else {
                    window.showFlash('error', data.message || 'Errore');
                }
            } catch (err) {
                window.showFlash('error', 'Errore di rete');
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
                    window.showFlash('success', data.message || 'Risposta eliminata');
                    loadQuestions();
                    updateMaxScoreInput();
                        updateQuizValidityBadge();
                } else {
                    window.showFlash('error', data.message || 'Errore');
                }
            }).catch(() => window.showFlash('error', 'Errore di rete'));
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
                    window.showFlash('success', data.message || 'Risposta corretta aggiornata');
                    loadQuestions();
                    updateMaxScoreInput();
                        updateQuizValidityBadge();
                } else {
                    window.showFlash('error', data.message || 'Errore');
                }
            }).catch(() => window.showFlash('error', 'Errore di rete'));
        }
    });
});
