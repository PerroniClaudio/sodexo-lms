/**
 * module-satisfaction-quiz.js
 * Gestisce i quiz di gradimento.
 */

import { getModuleRoot, getModuleData, escapeHtml, showError } from './module-base.js';

/**
 * Inizializza il modulo quiz di gradimento
 */
export function initSatisfactionQuizModule() {
    const root = getModuleRoot();
    if (!root) return;

    const moduleData = getModuleData(root);
    initQuizModule(moduleData, 'satisfaction_quiz');
}

/**
 * Inizializza il modulo quiz
 */
function initQuizModule(moduleData, quizType) {
    const wrapper = document.getElementById('module-player');
    const tpl = document.getElementById('tpl-quiz');
    
    if (!tpl) {
        console.warn(`[${quizType}] Template non trovato`);
        return;
    }

    wrapper.appendChild(tpl.content.cloneNode(true));

    const loadingEl = wrapper.querySelector('#quiz-loading');
    const quizContent = wrapper.querySelector('#quiz-content');
    const quizQuestionsEl = wrapper.querySelector('#quiz-questions');
    const quizForm = wrapper.querySelector('#quiz-form');
    const quizResult = wrapper.querySelector('#quiz-result');

    loadQuiz(
        moduleData,
        loadingEl,
        quizContent,
        quizQuestionsEl,
        quizForm,
        quizResult,
        quizType
    );
}

/**
 * Carica il quiz dal server
 */
async function loadQuiz(
    moduleData,
    loadingEl,
    quizContent,
    quizQuestionsEl,
    quizForm,
    quizResult,
    quizType
) {
    try {
        const response = await fetch(moduleData.quizUrl, {
            headers: { Accept: 'application/json' }
        });

        if (!response.ok) {
            throw new Error('Errore caricamento quiz');
        }

        const data = await response.json();

        if (data.completed) {
            loadingEl.classList.add('hidden');
            quizResult.classList.remove('hidden');
            renderQuizResult(data, quizResult);

            return;
        }

        renderQuizQuestions(data.questions, quizQuestionsEl);

        loadingEl.classList.add('hidden');
        quizContent.classList.remove('hidden');

        // Gestione submit
        quizForm.addEventListener('submit', (e) => {
            e.preventDefault();
            submitQuiz(
                data,
                quizForm,
                quizContent,
                quizResult,
                moduleData,
                quizType
            );
        });
    } catch (error) {
        console.error(`[${quizType}] Errore:`, error);
        if (loadingEl) {
            loadingEl.textContent = 'Impossibile caricare il quiz. Riprova più tardi.';
            loadingEl.classList.add('text-error');
        }
    }
}

/**
 * Renderizza le domande del quiz
 */
function renderQuizQuestions(questions, container) {
    container.innerHTML = '';
    
    questions.forEach((question, qIndex) => {
        const questionEl = document.createElement('div');
        questionEl.className = 'flex flex-col gap-3';
        questionEl.innerHTML = `
            <p class="font-semibold">${qIndex + 1}. ${escapeHtml(question.text)}</p>
            <div class="flex flex-col gap-2" id="answers-${question.id}"></div>
        `;

        const answersContainer = questionEl.querySelector(`#answers-${question.id}`);

        question.answers.forEach((answer) => {
            const label = document.createElement('label');
            label.className = 'flex items-center gap-3 cursor-pointer';
            label.innerHTML = `
                <input type="radio" class="radio radio-primary" name="answer_${question.id}" value="${answer.id}" required />
                <span>${escapeHtml(answer.text)}</span>
            `;
            answersContainer.appendChild(label);
        });

        container.appendChild(questionEl);
        
        if (qIndex < questions.length - 1) {
            container.appendChild(document.createElement('hr'));
        }
    });
}

/**
 * Invia il quiz al server
 */
async function submitQuiz(
    quizData,
    form,
    quizContent,
    quizResult,
    moduleData,
    quizType
) {
    const formData = new FormData(form);
    const answers = {};

    quizData.questions.forEach((q) => {
        const val = formData.get(`answer_${q.id}`);
        if (val !== null) {
            answers[q.id] = parseInt(val, 10);
        }
    });

    try {
        const response = await fetch(moduleData.quizSubmitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': moduleData.csrfToken,
            },
            body: JSON.stringify({
                template_id: quizData.template_id,
                answers,
            }),
        });

        const data = await response.json();

        quizContent.classList.add('hidden');
        quizResult.classList.remove('hidden');

        if (data.error) {
            showError(data.error, quizResult);
            return;
        }

        renderQuizResult(data, quizResult);
    } catch (error) {
        console.error(`[${quizType}] Errore submit:`, error);
        quizResult.innerHTML = '<div class="alert alert-error"><span>Errore nell\'invio delle risposte.</span></div>';
        quizContent.classList.add('hidden');
        quizResult.classList.remove('hidden');
    }
}

/**
 * Renderizza il risultato del quiz di gradimento
 * (per i quiz di gradimento non c'è concetto di pass/fail)
 */
function renderQuizResult(data, quizResult) {
    quizResult.innerHTML = `
        <div class="alert alert-success">
            <span>${escapeHtml(data.message || 'Grazie per aver completato il questionario di gradimento!')}</span>
        </div>
    `;
}
