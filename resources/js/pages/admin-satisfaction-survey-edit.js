document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-satisfaction-survey-page]');

    if (!page) {
        return;
    }

    const indexUrl = page.dataset.indexUrl;
    const storeUrl = page.dataset.storeUrl;
    const reorderUrl = page.dataset.reorderUrl;
    const courseTypeLabels = JSON.parse(page.dataset.courseTypeLabels || '{}');
    const list = page.querySelector('[data-questions-list]');
    const emptyState = page.querySelector('[data-empty-state]');
    const summary = page.querySelector('[data-questions-summary]');
    const loading = page.querySelector('[data-questions-loading]');
    const questionModal = page.querySelector('[data-question-modal]');
    const questionForm = page.querySelector('[data-question-form]');
    const questionModalTitle = page.querySelector('[data-question-modal-title]');
    const closeQuestionModalButtons = page.querySelectorAll('[data-close-question-modal]');
    const openCreateModalButton = page.querySelector('[data-open-create-modal]');
    const inputTypeField = questionForm?.querySelector('[name="input_type"]');
    const answersPanel = page.querySelector('[data-answers-panel]');
    const answersFields = page.querySelector('[data-answers-fields]');
    const formError = page.querySelector('[data-question-form-error]');
    const deleteModal = page.querySelector('[data-delete-modal]');
    const deleteDescription = page.querySelector('[data-delete-description]');
    const closeDeleteModalButtons = page.querySelectorAll('[data-close-delete-modal]');
    const confirmDeleteButton = page.querySelector('[data-confirm-delete]');

    if (!indexUrl || !storeUrl || !reorderUrl || !list || !emptyState || !summary || !loading || !(questionModal instanceof HTMLDialogElement) || !questionForm || !questionModalTitle || closeQuestionModalButtons.length === 0 || !(deleteModal instanceof HTMLDialogElement) || !deleteDescription || closeDeleteModalButtons.length === 0 || !confirmDeleteButton || !(inputTypeField instanceof HTMLSelectElement) || !answersPanel || !answersFields || !formError) {
        return;
    }

    const state = {
        questions: [],
        editingQuestionId: null,
        deletingQuestionId: null,
        loading: false,
        savingOrder: false,
        draggedId: null,
    };

    const inputTypeLabels = {
        radio: 'Risposta multipla',
        textarea: 'Testo libero',
    };
    const moveIcon = page.querySelector('[data-move-icon-template]')?.innerHTML?.trim() || '';

    const escapeHtml = (value) => String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const createAnswerField = (value = '', index = 0) => `
        <label class="form-control gap-2">
            <span class="label-text text-sm">${index + 1}. ${escapeHtml('Risposta')}</span>
            <input type="text" name="answers[]" value="${escapeHtml(value)}" class="input input-bordered w-full">
        </label>
    `;

    const renderAnswerFields = (answers = []) => {
        answersFields.innerHTML = '';

        for (let index = 0; index < 5; index += 1) {
            answersFields.insertAdjacentHTML('beforeend', createAnswerField(answers[index]?.text || answers[index] || '', index));
        }
    };

    const toggleAnswerPanel = () => {
        const usesTextarea = inputTypeField.value === 'textarea';
        answersPanel.classList.toggle('hidden', usesTextarea);
        answersFields.querySelectorAll('input').forEach((input) => {
            input.required = !usesTextarea;
        });
    };

    const resetForm = () => {
        state.editingQuestionId = null;
        questionForm.reset();
        questionForm.elements.namedItem('question_id').value = '';
        questionModalTitle.textContent = 'Nuova domanda';
        renderAnswerFields();
        questionForm.querySelectorAll('input[name="excluded_course_types[]"]').forEach((checkbox) => {
            checkbox.checked = false;
        });
        formError.textContent = '';
        formError.classList.add('hidden');
        inputTypeField.value = 'radio';
        toggleAnswerPanel();
    };

    const setLoading = (isLoading) => {
        state.loading = isLoading;
        loading.classList.toggle('hidden', !isLoading);
    };

    const renderSummary = () => {
        if (state.questions.length === 0) {
            summary.textContent = 'Nessuna domanda configurata.';

            return;
        }

        const textareas = state.questions.filter((question) => question.input_type === 'textarea').length;
        summary.textContent = `${state.questions.length} domande configurate, di cui ${textareas} aperte sempre in fondo.`;
    };

    const renderQuestions = () => {
        list.innerHTML = '';
        emptyState.classList.toggle('hidden', state.questions.length > 0);
        renderSummary();

        state.questions.forEach((question, index) => {
            const excludedTypes = (question.excluded_course_types || [])
                .map((courseType) => courseTypeLabels[courseType] || courseType.toUpperCase());

            const item = document.createElement('article');
            item.className = 'rounded-box border border-base-300 bg-base-100 p-4 shadow-sm';
            item.setAttribute('data-question-item', '');
            item.setAttribute('data-question-id', String(question.id));
            item.draggable = true;
            item.innerHTML = `
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge badge-outline gap-1 cursor-move">
                                ${moveIcon}
                                <span>${index + 1}</span>
                            </span>
                            <span class="badge ${question.input_type === 'textarea' ? 'badge-accent' : 'badge-primary'}">${escapeHtml(inputTypeLabels[question.input_type] || question.input_type)}</span>
                            ${question.input_type === 'textarea'
                                ? '<span class="badge badge-warning badge-outline">Sempre in fondo</span>'
                                : ''}
                        </div>
                        <p class="font-semibold">${escapeHtml(question.text)}</p>
                        ${question.input_type === 'radio'
                            ? `<ol class="ml-5 list-decimal space-y-1 text-sm text-base-content/70">${question.answers.map((answer) => `<li>${escapeHtml(answer.text)}</li>`).join('')}</ol>`
                            : '<p class="text-sm text-base-content/70">Risposta libera tramite textarea.</p>'}
                        <div class="flex flex-wrap gap-2 text-xs">
                            ${excludedTypes.length > 0
                                ? excludedTypes.map((label) => `<span class="badge badge-ghost">${escapeHtml(label)} salta</span>`).join('')
                                : '<span class="text-base-content/60">Nessuna tipologia esclusa.</span>'}
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" class="btn btn-primary btn-sm" data-action="edit">Modifica</button>
                        <button type="button" class="btn btn-error btn-outline btn-sm" data-action="delete">Elimina</button>
                    </div>
                </div>
            `;

            item.querySelector('[data-action="edit"]').addEventListener('click', () => openEditModal(question.id));
            item.querySelector('[data-action="delete"]').addEventListener('click', () => openDeleteModal(question.id));

            item.addEventListener('dragstart', () => {
                state.draggedId = question.id;
                item.classList.add('opacity-60', 'ring-2', 'ring-primary/30');
            });

            item.addEventListener('dragend', async () => {
                item.classList.remove('opacity-60', 'ring-2', 'ring-primary/30');

                if (state.draggedId === null || state.savingOrder) {
                    state.draggedId = null;

                    return;
                }

                state.draggedId = null;
                await persistOrder();
            });

            list.appendChild(item);
        });
    };

    const loadQuestions = async () => {
        setLoading(true);

        try {
            const response = await window.axios.get(indexUrl, {
                headers: { Accept: 'application/json' },
            });

            state.questions = response.data.data || [];
            renderQuestions();
        } catch (error) {
            window.showFlash?.('error', error.response?.data?.message || 'Errore nel caricamento delle domande.');
        } finally {
            setLoading(false);
        }
    };

    const openEditModal = (questionId) => {
        const question = state.questions.find((item) => item.id === questionId);

        if (!question) {
            return;
        }

        resetForm();
        state.editingQuestionId = question.id;
        questionForm.elements.namedItem('question_id').value = String(question.id);
        questionForm.elements.namedItem('text').value = question.text;
        inputTypeField.value = question.input_type;
        renderAnswerFields(question.answers || []);
        questionForm.querySelectorAll('input[name="excluded_course_types[]"]').forEach((checkbox) => {
            checkbox.checked = (question.excluded_course_types || []).includes(checkbox.value);
        });
        questionModalTitle.textContent = 'Modifica domanda';
        toggleAnswerPanel();
        questionModal.showModal();
    };

    const openDeleteModal = (questionId) => {
        const question = state.questions.find((item) => item.id === questionId);

        if (!question) {
            return;
        }

        state.deletingQuestionId = question.id;
        deleteDescription.textContent = `Vuoi eliminare la domanda "${question.text}"?`;
        deleteModal.showModal();
    };

    const persistOrder = async () => {
        const orderedIds = Array.from(list.querySelectorAll('[data-question-item]')).map((item) => Number(item.dataset.questionId));

        state.savingOrder = true;

        try {
            const response = await window.axios.patch(reorderUrl, {
                question_ids: orderedIds,
            }, {
                headers: { Accept: 'application/json' },
            });

            state.questions = response.data.questions || [];
            renderQuestions();
            window.showFlash?.('success', response.data.message || 'Ordine aggiornato con successo.');
        } catch (error) {
            window.showFlash?.('error', error.response?.data?.message || 'Errore durante il salvataggio dell\'ordine.');
            await loadQuestions();
        } finally {
            state.savingOrder = false;
        }
    };

    list.addEventListener('dragover', (event) => {
        event.preventDefault();

        if (state.draggedId === null || state.savingOrder) {
            return;
        }

        const target = event.target.closest('[data-question-item]');
        const dragged = list.querySelector(`[data-question-id="${state.draggedId}"]`);

        if (!target || !dragged || target === dragged) {
            return;
        }

        const targetBounds = target.getBoundingClientRect();
        const shouldInsertAfter = event.clientY > targetBounds.top + (targetBounds.height / 2);

        if (shouldInsertAfter) {
            list.insertBefore(dragged, target.nextSibling);
        } else {
            list.insertBefore(dragged, target);
        }
    });

    openCreateModalButton?.addEventListener('click', () => {
        resetForm();
        questionModal.showModal();
    });

    closeQuestionModalButtons.forEach((button) => {
        button.addEventListener('click', () => questionModal.close());
    });

    closeDeleteModalButtons.forEach((button) => {
        button.addEventListener('click', () => deleteModal.close());
    });

    inputTypeField.addEventListener('change', toggleAnswerPanel);

    questionForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        formError.textContent = '';
        formError.classList.add('hidden');

        const formData = new FormData(questionForm);
        const payload = {
            text: String(formData.get('text') || '').trim(),
            input_type: String(formData.get('input_type') || 'radio'),
            excluded_course_types: formData.getAll('excluded_course_types[]').map((value) => String(value)),
            answers: formData.getAll('answers[]').map((value) => String(value)),
        };

        const questionId = questionForm.elements.namedItem('question_id').value;
        const method = questionId ? 'put' : 'post';
        const url = questionId ? `${indexUrl}/${questionId}` : storeUrl;
        const submitButton = page.querySelector('[data-question-submit]');

        submitButton?.setAttribute('disabled', 'disabled');

        try {
            const response = await window.axios[method](url, payload, {
                headers: { Accept: 'application/json' },
            });

            state.questions = response.data.questions || state.questions;
            renderQuestions();
            questionModal.close();
            window.showFlash?.('success', response.data.message || 'Operazione completata con successo.');
        } catch (error) {
            const firstValidationError = Object.values(error.response?.data?.errors || {})[0]?.[0];
            const message = firstValidationError || error.response?.data?.message || 'Errore durante il salvataggio della domanda.';
            formError.textContent = message;
            formError.classList.remove('hidden');
        } finally {
            submitButton?.removeAttribute('disabled');
        }
    });

    confirmDeleteButton.addEventListener('click', async () => {
        if (state.deletingQuestionId === null) {
            return;
        }

        confirmDeleteButton.setAttribute('disabled', 'disabled');

        try {
            const response = await window.axios.delete(`${indexUrl}/${state.deletingQuestionId}`, {
                headers: { Accept: 'application/json' },
            });

            state.questions = response.data.questions || [];
            renderQuestions();
            deleteModal.close();
            window.showFlash?.('success', response.data.message || 'Domanda eliminata con successo.');
        } catch (error) {
            window.showFlash?.('error', error.response?.data?.message || 'Errore durante l\'eliminazione della domanda.');
        } finally {
            state.deletingQuestionId = null;
            confirmDeleteButton.removeAttribute('disabled');
        }
    });

    resetForm();
    void loadQuestions();
});
