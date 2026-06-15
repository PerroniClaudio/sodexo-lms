import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

document.addEventListener('DOMContentLoaded', () => {
    const moduleEditPage = document.querySelector('[data-module-edit-page]');

    if (!moduleEditPage) {
        return;
    }

    initializeAssignmentDialog(
        moduleEditPage,
        '#assign-teachers-modal',
        '[data-open-teacher-assignment-modal]',
        '[data-close-teacher-assignment-modal]',
        'hasTeacherAssignmentErrors',
    );
    initializeAssignmentDialog(
        moduleEditPage,
        '#assign-tutors-modal',
        '[data-open-tutor-assignment-modal]',
        '[data-close-tutor-assignment-modal]',
        'hasTutorAssignmentErrors',
    );
    initializeAssignmentDialog(
        moduleEditPage,
        '#confirm-attendance-modal',
        '[data-open-attendance-confirmation-modal]',
        '[data-close-attendance-confirmation-modal]',
        'hasAttendanceConfirmationErrors',
    );
    initializeTargetDialogs(moduleEditPage, '[data-open-staff-removal-modal]');
    initializeVideoExerciseEditors(document);
    initializeVideoExerciseMaterialModal(document);
});

function initializeAssignmentDialog(moduleEditPage, modalSelector, openButtonSelector, closeButtonSelector, errorFlag) {
    const modal = document.querySelector(modalSelector);
    const openButton = document.querySelector(openButtonSelector);
    const closeButton = document.querySelector(closeButtonSelector);

    if (!modal || !openButton || !closeButton) {
        return;
    }

    openButton.addEventListener('click', () => {
        modal.showModal();
    });

    closeButton.addEventListener('click', () => {
        modal.close();
    });

    if (moduleEditPage.dataset[errorFlag] === 'true') {
        modal.showModal();
    }
}

function initializeTargetDialogs(moduleEditPage, openButtonSelector) {
    const openButtons = moduleEditPage.querySelectorAll(openButtonSelector);

    if (openButtons.length === 0) {
        return;
    }

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const modal = moduleEditPage.querySelector(button.dataset.modalTarget);

            if (modal) {
                modal.showModal();
            }
        });
    });
}

function initializeVideoExerciseEditors(moduleEditPage) {
    moduleEditPage.querySelectorAll('[data-module-tiptap-editor]').forEach((element) => {
        const target = document.getElementById(element.dataset.target);

        if (!target) {
            return;
        }

        const editor = new Editor({
            element,
            extensions: [StarterKit],
            content: target.value,
            editorProps: {
                attributes: {
                    class: 'min-h-28 focus:outline-none prose max-w-none',
                },
            },
            onUpdate({ editor }) {
                target.value = editor.getHTML();
            },
        });

        moduleEditPage.querySelectorAll(`[data-module-tiptap-toolbar="${target.id}"] [data-command]`).forEach((button) => {
            button.addEventListener('click', () => {
                const chain = editor.chain().focus();
                const command = button.dataset.command;

                if (command === 'bold') {
                    chain.toggleBold().run();
                    return;
                }

                if (command === 'italic') {
                    chain.toggleItalic().run();
                    return;
                }

                if (command === 'heading') {
                    chain.toggleHeading({ level: Number(button.dataset.level) }).run();
                    return;
                }

                if (command === 'paragraph') {
                    chain.setParagraph().run();
                    return;
                }

                if (command === 'bulletList') {
                    chain.toggleBulletList().run();
                    return;
                }

                if (command === 'orderedList') {
                    chain.toggleOrderedList().run();
                    return;
                }

                if (command === 'undo') {
                    chain.undo().run();
                    return;
                }

                if (command === 'redo') {
                    chain.redo().run();
                }
            });
        });
    });
}

function initializeVideoExerciseMaterialModal(root) {
    const modal = root.querySelector('[data-video-exercise-material-modal]');

    if (!modal) {
        return;
    }

    const firstStep = modal.querySelector('[data-material-step="1"]');
    const secondStep = modal.querySelector('[data-material-step="2"]');
    const typeSelect = modal.querySelector('[data-material-type]');
    const nextButton = modal.querySelector('[data-material-next]');
    const backButton = modal.querySelector('[data-material-back]');
    const submitButton = modal.querySelector('[data-material-submit]');
    const fields = modal.querySelectorAll('[data-material-field]');

    if (!firstStep || !secondStep || !typeSelect || !nextButton || !backButton || !submitButton) {
        return;
    }

    const showStep = (step) => {
        firstStep.classList.toggle('hidden', step !== 1);
        secondStep.classList.toggle('hidden', step !== 2);
        nextButton.classList.toggle('hidden', step !== 1);
        backButton.classList.toggle('hidden', step !== 2);
        submitButton.classList.toggle('hidden', step !== 2);
    };

    const syncTypeFields = () => {
        fields.forEach((field) => {
            const active = field.dataset.materialField === typeSelect.value;
            field.classList.toggle('hidden', !active);
            field.querySelectorAll('input, textarea').forEach((input) => {
                input.disabled = !active;
            });
        });
    };

    nextButton.addEventListener('click', () => {
        syncTypeFields();
        showStep(2);
    });

    backButton.addEventListener('click', () => {
        showStep(1);
    });

    typeSelect.addEventListener('change', syncTypeFields);
    syncTypeFields();
    showStep(1);
}
