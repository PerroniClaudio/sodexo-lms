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
