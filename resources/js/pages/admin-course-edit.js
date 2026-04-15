const typesWithoutManualTitle = new Set(['learning_quiz', 'satisfaction_quiz']);

document.addEventListener('DOMContentLoaded', () => {
    const courseEditPage = document.querySelector('[data-course-edit-page]');

    if (!courseEditPage) {
        return;
    }

    initializeCreateModuleDialog(courseEditPage);
    initializeDeleteCourseDialog(courseEditPage);
    initializeDeleteModuleDialogs(courseEditPage);
    initializeModuleSorting(courseEditPage);
});

function initializeCreateModuleDialog(courseEditPage) {
    const createModuleModal = courseEditPage.querySelector('#create-module-modal');
    const openModalButton = courseEditPage.querySelector('[data-open-module-modal]');
    const closeModalButton = courseEditPage.querySelector('[data-close-module-modal]');
    const titleFieldWrapper = courseEditPage.querySelector('#module-title-field');
    const titleInput = courseEditPage.querySelector('#module-title');
    const typeInputs = courseEditPage.querySelectorAll('input[name="type"]');

    if (!createModuleModal || !openModalButton || !closeModalButton || !titleFieldWrapper || !titleInput || typeInputs.length === 0) {
        return;
    }

    const syncTitleFieldVisibility = () => {
        const selectedType = courseEditPage.querySelector('input[name="type"]:checked')?.value;
        const requiresManualTitle = selectedType && !typesWithoutManualTitle.has(selectedType);

        titleFieldWrapper.classList.toggle('hidden', !requiresManualTitle);
        titleInput.toggleAttribute('disabled', !requiresManualTitle);

        if (!requiresManualTitle) {
            titleInput.value = '';
        }
    };

    openModalButton.addEventListener('click', () => {
        createModuleModal.showModal();
    });

    closeModalButton.addEventListener('click', () => {
        createModuleModal.close();
    });

    typeInputs.forEach((input) => {
        input.addEventListener('change', syncTitleFieldVisibility);
    });

    if (courseEditPage.dataset.hasCreateModuleErrors === 'true') {
        createModuleModal.showModal();
    }

    syncTitleFieldVisibility();
}

function initializeDeleteCourseDialog(courseEditPage) {
    const deleteCourseModal = courseEditPage.querySelector('#delete-course-modal');
    const openDeleteCourseModalButton = courseEditPage.querySelector('[data-open-delete-course-modal]');

    if (!deleteCourseModal || !openDeleteCourseModalButton) {
        return;
    }

    openDeleteCourseModalButton.addEventListener('click', () => {
        deleteCourseModal.showModal();
    });
}

function initializeDeleteModuleDialogs(courseEditPage) {
    const openDeleteModuleModalButtons = courseEditPage.querySelectorAll('[data-open-delete-module-modal]');

    if (openDeleteModuleModalButtons.length === 0) {
        return;
    }

    openDeleteModuleModalButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const modal = courseEditPage.querySelector(button.dataset.modalTarget);

            if (modal) {
                modal.showModal();
            }
        });
    });
}

function initializeModuleSorting(courseEditPage) {
    const sortableList = courseEditPage.querySelector('[data-modules-sortable-list]');

    if (!sortableList) {
        return;
    }

    let draggedItem = null;
    let previousOrder = [];
    let isSaving = false;

    const getItems = () => Array.from(sortableList.querySelectorAll('[data-module-item]'));
    const getCurrentOrder = () => getItems().map((item) => Number(item.dataset.moduleId));
    const setSavingState = (saving) => {
        sortableList.classList.toggle('pointer-events-none', saving);
        sortableList.classList.toggle('opacity-70', saving);
    };

    const restoreOrder = (moduleIds) => {
        const itemsById = new Map(getItems().map((item) => [Number(item.dataset.moduleId), item]));

        moduleIds.forEach((moduleId) => {
            const item = itemsById.get(moduleId);

            if (item) {
                sortableList.appendChild(item);
            }
        });
    };

    const persistOrder = async () => {
        const reorderedModuleIds = getCurrentOrder();

        if (previousOrder.length === 0 || reorderedModuleIds.join(',') === previousOrder.join(',')) {
            return;
        }

        isSaving = true;
        setSavingState(true);

        try {
            await window.axios.patch(
                sortableList.dataset.reorderUrl,
                { modules: reorderedModuleIds },
                { headers: { Accept: 'application/json' } },
            );
        } catch (error) {
            restoreOrder(previousOrder);
            window.location.reload();
        } finally {
            isSaving = false;
            setSavingState(false);
            previousOrder = [];
        }
    };

    sortableList.addEventListener('dragover', (event) => {
        if (isSaving || draggedItem === null) {
            return;
        }

        event.preventDefault();

        const targetItem = event.target.closest('[data-module-item]');

        if (!targetItem || targetItem === draggedItem) {
            return;
        }

        const targetBounds = targetItem.getBoundingClientRect();
        const shouldInsertAfter = event.clientY > targetBounds.top + targetBounds.height / 2;

        if (shouldInsertAfter) {
            sortableList.insertBefore(draggedItem, targetItem.nextSibling);
        } else {
            sortableList.insertBefore(draggedItem, targetItem);
        }
    });

    getItems().forEach((item) => {
        item.addEventListener('dragstart', () => {
            if (isSaving) {
                return;
            }

            draggedItem = item;
            previousOrder = getCurrentOrder();
            item.classList.add('opacity-50', 'shadow-lg', 'ring-2', 'ring-primary/30');
        });

        item.addEventListener('dragend', async () => {
            if (draggedItem === null) {
                return;
            }

            draggedItem.classList.remove('opacity-50', 'shadow-lg', 'ring-2', 'ring-primary/30');
            draggedItem = null;
            await persistOrder();
        });
    });
}
