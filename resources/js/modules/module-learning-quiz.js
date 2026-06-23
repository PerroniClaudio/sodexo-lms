/**
 * module-learning-quiz.js
 * Gestisce i quiz di apprendimento con flow step-by-step.
 */

import { getModuleRoot, getModuleData, refreshModulePlayerState } from './module-base.js';

function cloneTemplateElement(root, selector) {
    const template = root.querySelector(selector);

    if (!(template instanceof HTMLTemplateElement)) {
        return null;
    }

    const element = template.content.firstElementChild;

    if (!element) {
        return null;
    }

    return element.cloneNode(true);
}

/**
 * Inizializza il modulo quiz di apprendimento
 */
export function initLearningQuizModule() {
    const root = getModuleRoot();
    if (!root) return;

    const moduleData = getModuleData(root);

    if (moduleData.quizAccessGateActive) {
        initQuizAccessGate(moduleData);

        return;
    }

    // Carica lo stato del quiz
    loadQuizStatus(moduleData);

    // Previeni ricaricamento pagina durante quiz attivo
    preventPageReload();
}

function initQuizAccessGate(moduleData) {
    const timerElement = document.querySelector('[data-quiz-access-gate-timer]');

    if (!timerElement) {
        return;
    }

    let remainingSeconds = Math.max(0, moduleData.quizAccessGateRemainingSeconds || 0);

    const renderTime = () => {
        const hours = String(Math.floor(remainingSeconds / 3600)).padStart(2, '0');
        const minutes = String(Math.floor((remainingSeconds % 3600) / 60)).padStart(2, '0');
        const seconds = String(remainingSeconds % 60).padStart(2, '0');

        timerElement.textContent = `${hours}:${minutes}:${seconds}`;
    };

    renderTime();

    const interval = window.setInterval(() => {
        remainingSeconds -= 1;

        if (remainingSeconds <= 0) {
            window.clearInterval(interval);
            timerElement.textContent = '00:00:00';
            window.location.reload();

            return;
        }

        renderTime();
    }, 1000);
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
    const root = getModuleRoot();
    const statusContainer = document.getElementById('quiz-status');
    const cardBody = statusContainer.querySelector('.card-body');
    const statusContent = cloneTemplateElement(root, '[data-learning-quiz-status-template]');

    if (!statusContent) {
        return;
    }

    const titleElement = statusContent.querySelector('[data-quiz-status-title]');
    const statsContainer = statusContent.querySelector('[data-quiz-status-stats]');
    const pastAttemptsSection = statusContent.querySelector('[data-quiz-past-attempts]');
    const pastAttemptsBody = statusContent.querySelector('[data-quiz-past-attempts-body]');
    const passedAlert = statusContent.querySelector('[data-quiz-passed-alert]');
    const exhaustedAlert = statusContent.querySelector('[data-quiz-exhausted-alert]');
    const startAction = statusContent.querySelector('[data-quiz-start-action]');

    titleElement.textContent = data.module.title || '';
    statsContainer.replaceChildren();

    const appendStat = (title, value) => {
        const stat = cloneTemplateElement(root, '[data-learning-quiz-stat-template]');

        if (!stat) {
            return;
        }

        stat.querySelector('[data-quiz-stat-title]').textContent = title;
        stat.querySelector('[data-quiz-stat-value]').textContent = value;
        statsContainer.appendChild(stat);
    };

    appendStat('Tentativi usati', `${data.progress.attempts_used}${data.module.max_attempts ? ` / ${data.module.max_attempts}` : ''}`);

    if (data.progress.best_score !== null) {
        appendStat('Miglior punteggio', `${data.progress.best_score} / ${data.module.max_score}`);
    }

    appendStat('Punteggio minimo', `${data.module.passing_score} / ${data.module.max_score}`);

    if (data.past_attempts.length > 0) {
        pastAttemptsSection.classList.remove('hidden');
        pastAttemptsBody.replaceChildren();

        data.past_attempts.forEach((attempt) => {
            const row = cloneTemplateElement(root, '[data-learning-quiz-attempt-row-template]');

            if (!row) {
                return;
            }

            const date = new Date(attempt.submitted_at).toLocaleString('it-IT');
            const attemptWasAbandoned = attempt.status === 'abandoned';
            const resultClass = attemptWasAbandoned ? 'badge-warning' : (attempt.passed ? 'badge-success' : 'badge-error');
            const resultText = attemptWasAbandoned ? 'Abbandonato' : (attempt.passed ? 'Superato' : 'Non superato');
            const scoreText = attempt.score === null || attempt.total_score === null
                ? '-'
                : `${attempt.score} / ${attempt.total_score}`;

            row.querySelector('[data-quiz-attempt-date]').textContent = date;
            row.querySelector('[data-quiz-attempt-score]').textContent = scoreText;

            const resultBadge = row.querySelector('[data-quiz-attempt-result]');
            resultBadge.classList.add(resultClass);
            resultBadge.textContent = resultText;
            pastAttemptsBody.appendChild(row);
        });
    }

    if (data.progress.passed) {
        passedAlert.classList.remove('hidden');
    } else if (data.progress.can_start_new_attempt) {
        startAction.classList.remove('hidden');
    } else {
        exhaustedAlert.classList.remove('hidden');
    }

    cardBody.replaceChildren(statusContent);

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
    const root = getModuleRoot();
    document.getElementById('current-question').textContent = data.question_number;
    document.getElementById('total-questions').textContent = data.total_questions;
    document.getElementById('question-text').textContent = data.question.text;

    const answersContainer = document.getElementById('question-answers');
    answersContainer.replaceChildren();

    data.question.answers.forEach((answer) => {
        const label = cloneTemplateElement(root, '[data-learning-quiz-answer-template]');

        if (!label) {
            return;
        }

        label.querySelector('input[name="answer"]').value = String(answer.id);
        label.querySelector('[data-quiz-answer-text]').textContent = answer.text || '';
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

    const root = getModuleRoot();
    const submitBtn = document.querySelector('#question-form button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.replaceChildren();

    const loadingContent = cloneTemplateElement(root, '[data-learning-quiz-submit-loading-template]');

    if (loadingContent) {
        submitBtn.appendChild(loadingContent);
    } else {
        submitBtn.textContent = 'Salvataggio...';
    }

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
    const resultAlert = cloneTemplateElement(root, '[data-learning-quiz-result-alert-template]');

    if (!resultAlert) {
        return;
    }

    const resultIcon = resultAlert.querySelector('[data-quiz-result-icon]');
    const resultMessage = resultAlert.querySelector('[data-quiz-result-message]');
    const resultScore = resultAlert.querySelector('[data-quiz-result-score]');
    resultContent.replaceChildren();
    resultAlert.classList.add(data.passed ? 'alert-success' : 'alert-error');
    resultMessage.textContent = data.message || '';
    resultScore.textContent = `Punteggio: ${data.score} / ${data.total_score} (minimo richiesto: ${data.passing_score})`;

    if (data.passed) {
        resultIcon.innerHTML = '<circle cx="12" cy="12" r="10"></circle><path d="M9 12l2 2 4-4"></path>';
    } else {
        resultIcon.innerHTML = '<circle cx="12" cy="12" r="10"></circle><path d="M15 9l-6 6"></path><path d="M9 9l6 6"></path>';
    }

    resultContent.appendChild(resultAlert);

    if (data.passed && moduleData.nextModuleUrl) {
        const nextModuleAction = cloneTemplateElement(root, '[data-learning-quiz-next-module-template]');

        if (nextModuleAction) {
            const nextModuleLink = nextModuleAction.querySelector('[data-quiz-next-module-link]');
            const nextModuleTitle = nextModuleAction.querySelector('[data-quiz-next-module-title]');

            nextModuleLink.href = moduleData.nextModuleUrl;
            nextModuleTitle.textContent = moduleData.nextModuleTitle || 'Modulo successivo';
            resultContent.appendChild(nextModuleAction);
        }
    } else if (!data.passed) {
        const retryAction = cloneTemplateElement(root, '[data-learning-quiz-retry-template]');

        if (retryAction) {
            retryAction.querySelector('[data-quiz-retry-button]').addEventListener('click', () => {
                window.location.reload();
            });
            resultContent.appendChild(retryAction);
        }
    }

    resultContainer.classList.remove('hidden');

    if (data.passed) {
        refreshModulePlayerState().catch((error) => {
            console.warn('[learning-quiz] state refresh failed', error);
        });
    }
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
