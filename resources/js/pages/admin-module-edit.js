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
});

function initializeAssignmentDialog(moduleEditPage, modalSelector, openButtonSelector, closeButtonSelector, errorFlag) {
    const modal = moduleEditPage.querySelector(modalSelector);
    const openButton = moduleEditPage.querySelector(openButtonSelector);
    const closeButton = moduleEditPage.querySelector(closeButtonSelector);

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
