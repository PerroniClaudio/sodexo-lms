/**
 * module-learning-quiz.js
 * Gestisce i quiz di apprendimento con flow step-by-step.
 */

import { getModuleRoot, getModuleData, escapeHtml } from './module-base.js';

let currentSubmissionId = null;

/**
 * Inizializza il modulo quiz di apprendimento
 */
export function initLearningQuizModule() {
    const root = getModuleRoot();
    if (!root) return;

    const moduleData = getModuleData(root);

    // Carica lo stato del quiz
    loadQuizStatus(moduleData);

    // Previeni ricaricamento pagina durante quiz attivo
    preventPageReload();
}

/**
 * Carica lo stato del quiz
 */
async function loadQuizStatus(moduleData) {
    const statusContainer = document.getElementById('quiz-status');
    const statusLoading = document.getElementById('status-loading');

    try {
        const response = await fetch(
            moduleData.quizUrl.replace('/quiz', '/quiz/status'),
            { headers: { Accept: 'application/json' } }
        );

        if (!response.ok) throw new Error('Errore caricamento stato');

        const data = await response.json();

        statusLoading.classList.add('hidden');

        // Se c'è un tentativo in corso, riprendi
        if (data.active_submission) {
            statusContainer.classList.add('hidden');
            currentSubmissionId = data.active_submission.id;
            startQuizFlow(moduleData);
        } else {
            // Mostra lo stato e il bottone per iniziare
            renderQuizStatus(data, moduleData);
        }
    } catch (error) {
        console.error('[learning-quiz] Errore:', error);
        statusLoading.textContent = 'Impossibile caricare lo stato del quiz.';
        statusLoading.classList.add('text-error');
    }
}

/**
 * Renderizza lo stato del quiz
 */
function renderQuizStatus(data, moduleData) {
    const statusContainer = document.getElementById('quiz-status');
    const cardBody = statusContainer.querySelector('.card-body');

    let html = '<div class="flex flex-col gap-4">';

    // Informazioni sul quiz
    html += `<div><h3 class="text-lg font-semibold">${escapeHtml(data.module.title)}</h3></div>`;

    // Statistiche
    html += '<div class="stats shadow">';
    html += `<div class="stat"><div class="stat-title">Tentativi usati</div><div class="stat-value">${data.progress.attempts_used}${data.module.max_attempts ? ' / ' + data.module.max_attempts : ''}</div></div>`;
    
    if (data.progress.best_score !== null) {
        html += `<div class="stat"><div class="stat-title">Miglior punteggio</div><div class="stat-value">${data.progress.best_score} / ${data.module.max_score}</div></div>`;
    }

    html += `<div class="stat"><div class="stat-title">Punteggio minimo</div><div class="stat-value">${data.module.passing_score} / ${data.module.max_score}</div></div>`;
    html += '</div>';

    // Tentativi passati
    if (data.past_attempts.length > 0) {
        html += '<div class="divider">Tentativi precedenti</div>';
        html += '<div class="overflow-x-auto"><table class="table table-sm"><thead><tr><th>Data</th><th>Punteggio</th><th>Risultato</th></tr></thead><tbody>';
        
        data.past_attempts.forEach(attempt => {
            const date = new Date(attempt.submitted_at).toLocaleString('it-IT');
            const resultClass = attempt.passed ? 'badge-success' : 'badge-error';
            const resultText = attempt.passed ? 'Superato' : 'Non superato';
            
            html += `<tr><td>${date}</td><td>${attempt.score} / ${attempt.total_score}</td><td><span class="badge ${resultClass}">${resultText}</span></td></tr>`;
        });
        
        html += '</tbody></table></div>';
    }

    // Bottone per iniziare
    if (data.progress.passed) {
        html += '<div class="alert alert-success"><span>Quiz completato con successo!</span></div>';
    } else if (data.progress.can_start_new_attempt) {
        html += '<div class="flex justify-end mt-4"><button type="button" id="start-quiz-btn" class="btn btn-primary">Inizia il test</button></div>';
    } else {
        html += '<div class="alert alert-error"><span>Hai esaurito i tentativi disponibili.</span></div>';
    }

    html += '</div>';

    cardBody.innerHTML = html;

    // Aggiungi event listener al bottone
    const startBtn = document.getElementById('start-quiz-btn');
    if (startBtn) {
        startBtn.addEventListener('click', () => showStartModal(moduleData));
    }
}

/**
 * Mostra il modal di conferma
 */
function showStartModal(moduleData) {
    const modal = document.getElementById('start-quiz-modal');
    const confirmBtn = document.getElementById('confirm-start-quiz');

    confirmBtn.onclick = async () => {
        modal.close();
        await startQuizAttempt(moduleData);
    };

    modal.showModal();
}

/**
 * Inizia un nuovo tentativo
 */
async function startQuizAttempt(moduleData) {
    try {
        const response = await fetch(
            moduleData.quizUrl.replace('/quiz', '/quiz/start'),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': moduleData.csrfToken,
                },
            }
        );

        const data = await response.json();

        if (!response.ok) {
            alert(data.error || 'Errore durante l\'avvio del quiz');
            return;
        }

        currentSubmissionId = data.submission_id;

        // Nascondi status e mostra quiz attivo
        document.getElementById('quiz-status').classList.add('hidden');
        startQuizFlow(moduleData);
    } catch (error) {
        console.error('[learning-quiz] Errore start:', error);
        alert('Errore durante l\'avvio del quiz');
    }
}

/**
 * Avvia il flow del quiz (carica la prossima domanda)
 */
function startQuizFlow(moduleData) {
    document.getElementById('quiz-active').classList.remove('hidden');
    loadNextQuestion(moduleData);
}

/**
 * Carica la prossima domanda
 */
async function loadNextQuestion(moduleData) {
    const questionLoading = document.getElementById('question-loading');
    const questionContent = document.getElementById('question-content');

    questionLoading.classList.remove('hidden');
    questionContent.classList.add('hidden');

    try {
        const response = await fetch(
            moduleData.quizUrl.replace('/quiz', '/quiz/next-question'),
            { headers: { Accept: 'application/json' } }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Errore caricamento domanda');
        }

        if (data.completed) {
            // Tutte le domande completate, completa il quiz
            await completeQuizAttempt(moduleData);
            return;
        }

        renderQuestion(data, moduleData);

        questionLoading.classList.add('hidden');
        questionContent.classList.remove('hidden');
    } catch (error) {
        console.error('[learning-quiz] Errore next question:', error);
        questionLoading.textContent = error.message;
        questionLoading.classList.add('text-error');
    }
}

/**
 * Renderizza una domanda
 */
function renderQuestion(data, moduleData) {
    document.getElementById('current-question').textContent = data.question_number;
    document.getElementById('total-questions').textContent = data.total_questions;
    document.getElementById('question-text').textContent = data.question.text;

    const answersContainer = document.getElementById('question-answers');
    answersContainer.innerHTML = '';

    data.question.answers.forEach(answer => {
        const label = document.createElement('label');
        label.className = 'flex items-center gap-3 cursor-pointer p-4 border border-base-300 rounded-lg hover:bg-base-200';
        label.innerHTML = `
            <input type="radio" class="radio radio-primary" name="answer" value="${answer.id}" required />
            <span>${escapeHtml(answer.text)}</span>
        `;
        answersContainer.appendChild(label);
    });

    const form = document.getElementById('question-form');
    
    // Reset del bottone submit
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Conferma risposta';
    }
    
    form.onsubmit = async (e) => {
        e.preventDefault();
        await submitAnswer(data.question.id, moduleData);
    };
}

/**
 * Invia una risposta
 */
async function submitAnswer(questionId, moduleData) {
    const selectedAnswer = document.querySelector('input[name="answer"]:checked');
    
    if (!selectedAnswer) {
        alert('Seleziona una risposta');
        return;
    }

    const submitBtn = document.querySelector('#question-form button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="loading loading-spinner loading-sm"></span> Salvataggio...';

    try {
        const response = await fetch(
            moduleData.quizUrl.replace('/quiz', '/quiz/answer'),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': moduleData.csrfToken,
                },
                body: JSON.stringify({
                    question_id: questionId,
                    answer_id: parseInt(selectedAnswer.value, 10),
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Errore invio risposta');
        }

        // Carica la prossima domanda
        await loadNextQuestion(moduleData);
    } catch (error) {
        console.error('[learning-quiz] Errore submit answer:', error);
        alert(error.message);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Conferma risposta';
    }
}

/**
 * Completa il quiz e mostra il risultato
 */
async function completeQuizAttempt(moduleData) {
    try {
        const response = await fetch(
            moduleData.quizUrl.replace('/quiz', '/quiz/complete'),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': moduleData.csrfToken,
                },
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Errore completamento quiz');
        }

        // Nascondi quiz attivo e mostra risultato
        document.getElementById('quiz-active').classList.add('hidden');
        showQuizResult(data);
    } catch (error) {
        console.error('[learning-quiz] Errore complete:', error);
        alert(error.message);
    }
}

/**
 * Mostra il risultato finale
 */
function showQuizResult(data) {
    const root = getModuleRoot();
    const moduleData = getModuleData(root);
    const resultContainer = document.getElementById('quiz-result');
    const resultContent = document.getElementById('result-content');

    const alertClass = data.passed ? 'alert-success' : 'alert-error';
    const icon = data.passed ? 'check-circle' : 'x-circle';

    resultContent.innerHTML = `
        <div class="alert ${alertClass}">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="${data.passed ? 'M9 12l2 2 4-4' : 'M15 9l-6 6M9 9l6 6'}"/></svg>
            <div>
                <div class="font-semibold">${escapeHtml(data.message)}</div>
                <div class="text-sm">Punteggio: ${data.score} / ${data.total_score} (minimo richiesto: ${data.passing_score})</div>
            </div>
        </div>
    `;

    if (data.passed && moduleData.nextModuleUrl) {
        // Quiz superato: mostra bottone per modulo successivo
        resultContent.innerHTML += `
            <div class="flex justify-end gap-4 mt-4">
                <a href="${moduleData.nextModuleUrl}" class="btn btn-primary">
                    ${escapeHtml(moduleData.nextModuleTitle || 'Modulo successivo')}
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
        `;
    } else if (!data.passed) {
        // Quiz non superato: mostra bottone per riprovare
        resultContent.innerHTML += `
            <div class="flex justify-center gap-4 mt-4">
                <button onclick="window.location.reload()" class="btn btn-primary">Riprova</button>
            </div>
        `;
    }

    resultContainer.classList.remove('hidden');
}

/**
 * Previeni ricaricamento pagina durante quiz attivo
 */
function preventPageReload() {
    window.addEventListener('beforeunload', (e) => {
        const quizActive = document.getElementById('quiz-active');
        
        if (quizActive && !quizActive.classList.contains('hidden')) {
            e.preventDefault();
            e.returnValue = 'Hai un quiz in corso. Se esci perderai il tentativo!';
            return e.returnValue;
        }
    });
}
