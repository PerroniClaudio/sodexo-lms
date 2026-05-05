/**
 * user-module-player.js
 * Gestisce la fruizione dei moduli video e questionario di apprendimento/gradimento.
 */

const wrapper = document.getElementById('module-player');
const root = wrapper ? wrapper.closest('[data-module-id]') : null;

if (!root) {
    console.warn('[module-player] Root element non trovato.');
} else {
    const moduleType = root.dataset.moduleType;
    const csrfToken = root.dataset.csrf;

    if (moduleType === 'video') {
        initVideoModule();
    } else if (moduleType === 'learning_quiz' || moduleType === 'satisfaction_quiz') {
        initQuizModule();
    }

    /**
     * Modulo VIDEO
     */
    function initVideoModule() {
        const tpl = document.getElementById('tpl-video');
        if (!tpl) {
            return;
        }

        wrapper.appendChild(tpl.content.cloneNode(true));

        const loadingEl = wrapper.querySelector('#video-loading');
        const playerWrapper = wrapper.querySelector('#video-player-wrapper');
        const errorEl = wrapper.querySelector('#video-error');
        const completedMsg = wrapper.querySelector('#video-completed-msg');
        const playerContainer = wrapper.querySelector('[data-mux-player-container]');

        const signedPlaybackUrl = root.dataset.signedPlaybackUrl;
        const progressUrl = root.dataset.videoProgressUrl;
        const completeUrl = root.dataset.videoCompleteUrl;

        fetch(signedPlaybackUrl, { headers: { Accept: 'application/json' } })
            .then((r) => {
                if (!r.ok) {
                    throw new Error('Errore caricamento video');
                }
                return r.json();
            })
            .then((data) => {
                const muxPlayer = document.createElement('mux-player');
                muxPlayer.setAttribute('stream-type', 'on-demand');
                muxPlayer.setAttribute('src', `https://stream.mux.com/${data.playback_id}.m3u8?token=${data.token}`);
                muxPlayer.setAttribute('metadata-video-title', root.dataset.moduleTitle ?? '');
                muxPlayer.setAttribute('primary-color', '#2563eb');
                muxPlayer.setAttribute('accent-color', '#2563eb');
                muxPlayer.setAttribute('style', 'width:100%;border-radius:8px;');

                if (data.video_current_second && data.video_current_second > 0) {
                    muxPlayer.setAttribute('start-time', String(data.video_current_second));
                }

                playerContainer.appendChild(muxPlayer);

                loadingEl.classList.add('hidden');
                playerWrapper.classList.remove('hidden');

                let lastProgressSecond = data.video_current_second ?? 0;

                muxPlayer.addEventListener('timeupdate', () => {
                    const current = Math.floor(muxPlayer.currentTime ?? 0);
                    if (current - lastProgressSecond >= 10) {
                        lastProgressSecond = current;
                        sendVideoProgress(current, progressUrl, csrfToken);
                    }
                });

                muxPlayer.addEventListener('ended', () => {
                    sendVideoComplete(completeUrl, csrfToken, completedMsg);
                });
            })
            .catch(() => {
                loadingEl.classList.add('hidden');
                errorEl.classList.remove('hidden');
            });
    }

    function sendVideoProgress(currentSecond, url, token) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ current_second: currentSecond }),
        }).catch(() => {});
    }

    function sendVideoComplete(url, token, completedMsgEl) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({}),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.success && completedMsgEl) {
                    completedMsgEl.classList.remove('hidden');
                }
            })
            .catch(() => {});
    }

    /**
     * Modulo QUIZ (learning_quiz / satisfaction_quiz)
     */
    function initQuizModule() {
        const tpl = document.getElementById('tpl-quiz');
        if (!tpl) {
            return;
        }

        wrapper.appendChild(tpl.content.cloneNode(true));

        const loadingEl = wrapper.querySelector('#quiz-loading');
        const quizContent = wrapper.querySelector('#quiz-content');
        const quizQuestionsEl = wrapper.querySelector('#quiz-questions');
        const quizForm = wrapper.querySelector('#quiz-form');
        const quizResult = wrapper.querySelector('#quiz-result');

        const quizUrl = root.dataset.quizUrl;
        const quizSubmitUrl = root.dataset.quizSubmitUrl;
        const passingScore = parseInt(root.dataset.passingScore ?? '0', 10);

        fetch(quizUrl, { headers: { Accept: 'application/json' } })
            .then((r) => {
                if (!r.ok) {
                    throw new Error('Errore caricamento quiz');
                }
                return r.json();
            })
            .then((data) => {
                renderQuizQuestions(data.questions, quizQuestionsEl);

                loadingEl.classList.add('hidden');
                quizContent.classList.remove('hidden');

                quizForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    submitQuiz(data, quizForm, quizContent, quizResult, quizSubmitUrl, csrfToken, passingScore);
                });
            })
            .catch(() => {
                if (loadingEl) {
                    loadingEl.textContent = 'Impossibile caricare il quiz. Riprova più tardi.';
                    loadingEl.classList.add('text-error');
                }
            });
    }

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

    function submitQuiz(quizData, form, quizContent, quizResult, url, token, passingScore) {
        const formData = new FormData(form);
        const answers = {};

        quizData.questions.forEach((q) => {
            const val = formData.get(`answer_${q.id}`);
            if (val !== null) {
                answers[q.id] = parseInt(val, 10);
            }
        });

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ answers }),
        })
            .then((r) => r.json())
            .then((data) => {
                quizContent.classList.add('hidden');
                quizResult.classList.remove('hidden');

                if (data.error) {
                    quizResult.innerHTML = `<div class="alert alert-error"><span>${escapeHtml(data.error)}</span></div>`;
                    return;
                }

                const score = parseInt(data.score, 10);
                const totalScore = parseInt(data.total_score, 10);
                const scorePassing = parseInt(data.passing_score, 10);
                const passed = data.passed;
                const alertClass = passed ? 'alert-success' : 'alert-warning';
                const message = passed
                    ? `Hai superato il quiz! Punteggio: ${score}/${totalScore}`
                    : `Punteggio insufficiente: ${score}/${totalScore}. Punteggio minimo richiesto: ${scorePassing}.`;

                quizResult.innerHTML = `
                    <div class="alert ${alertClass}">
                        <span>${escapeHtml(message)}</span>
                    </div>
                    ${!passed ? '<div class="flex justify-end mt-4"><button class="btn btn-primary" id="retry-quiz-btn">Riprova</button></div>' : ''}
                `;

                if (!passed) {
                    quizResult.querySelector('#retry-quiz-btn')?.addEventListener('click', () => {
                        quizResult.classList.add('hidden');
                        quizContent.classList.remove('hidden');
                        form.reset();
                    });
                }
            })
            .catch(() => {
                quizResult.innerHTML = '<div class="alert alert-error"><span>Errore nell\'invio delle risposte.</span></div>';
                quizContent.classList.add('hidden');
                quizResult.classList.remove('hidden');
            });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }
}
