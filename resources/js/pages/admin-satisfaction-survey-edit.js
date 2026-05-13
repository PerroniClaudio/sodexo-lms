document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-satisfaction-survey-editor]');

    if (!root) {
        return;
    }

    const questionsContainer = root.querySelector('[data-questions-container]');
    const addQuestionButton = root.querySelector('[data-add-question]');

    if (!questionsContainer || !addQuestionButton) {
        return;
    }

    const renumberFields = () => {
        Array.from(questionsContainer.querySelectorAll('[data-question-block]')).forEach((questionBlock, questionIndex) => {
            const title = questionBlock.querySelector('h3');

            if (title) {
                title.textContent = `Domanda ${questionIndex + 1}`;
            }

            const questionTextarea = questionBlock.querySelector('textarea');

            if (questionTextarea) {
                questionTextarea.name = `questions[${questionIndex}][text]`;
            }

            Array.from(questionBlock.querySelectorAll('[data-answer-row] input')).forEach((input, answerIndex) => {
                input.name = `questions[${questionIndex}][answers][${answerIndex}]`;
            });
        });
    };

    const createAnswerRow = (value = '') => {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-3';
        row.setAttribute('data-answer-row', '');
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'input input-bordered w-full';
        input.required = true;
        input.value = value;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-ghost btn-sm';
        button.setAttribute('data-remove-answer', '');
        button.textContent = 'Rimuovi';

        row.appendChild(input);
        row.appendChild(button);

        return row;
    };

    const bindQuestionBlock = (questionBlock) => {
        questionBlock.querySelector('[data-add-answer]')?.addEventListener('click', () => {
            const answersContainer = questionBlock.querySelector('[data-answers-container]');

            if (!answersContainer) {
                return;
            }

            answersContainer.appendChild(createAnswerRow());
            renumberFields();
        });

        questionBlock.querySelector('[data-remove-question]')?.addEventListener('click', () => {
            if (questionsContainer.querySelectorAll('[data-question-block]').length <= 1) {
                return;
            }

            questionBlock.remove();
            renumberFields();
        });

        questionBlock.addEventListener('click', (event) => {
            const target = event.target;

            if (!(target instanceof HTMLElement) || !target.matches('[data-remove-answer]')) {
                return;
            }

            const answersContainer = questionBlock.querySelector('[data-answers-container]');

            if (!answersContainer || answersContainer.querySelectorAll('[data-answer-row]').length <= 2) {
                return;
            }

            target.closest('[data-answer-row]')?.remove();
            renumberFields();
        });
    };

    addQuestionButton.addEventListener('click', () => {
        const questionBlock = document.createElement('div');
        questionBlock.className = 'rounded-box border border-base-300 bg-base-100 p-4';
        questionBlock.setAttribute('data-question-block', '');
        questionBlock.innerHTML = `
            <div class="flex items-center justify-between gap-4">
                <h3 class="font-semibold">Domanda</h3>
                <button type="button" class="btn btn-ghost btn-sm" data-remove-question>Rimuovi</button>
            </div>
            <div class="mt-4 flex flex-col gap-4">
                <div class="form-control flex flex-col gap-2">
                    <label class="label p-0">
                        <span class="label-text font-medium">Testo domanda</span>
                    </label>
                    <textarea class="textarea textarea-bordered min-h-24 w-full" required></textarea>
                </div>
                <div class="flex flex-col gap-3" data-answers-container></div>
                <div>
                    <button type="button" class="btn btn-outline btn-sm" data-add-answer>Aggiungi risposta</button>
                </div>
            </div>
        `;

        const answersContainer = questionBlock.querySelector('[data-answers-container]');

        answersContainer?.appendChild(createAnswerRow());
        answersContainer?.appendChild(createAnswerRow());

        questionsContainer.appendChild(questionBlock);
        bindQuestionBlock(questionBlock);
        renumberFields();
    });

    Array.from(questionsContainer.querySelectorAll('[data-question-block]')).forEach(bindQuestionBlock);
    renumberFields();
});
