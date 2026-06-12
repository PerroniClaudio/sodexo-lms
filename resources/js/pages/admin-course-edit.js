import { setButtonLoading, toggleAsyncTableLoading } from '../ui/loading-state';

const typesWithoutManualTitle = new Set(['learning_quiz', 'satisfaction_quiz']);

document.addEventListener('DOMContentLoaded', () => {
    const courseEditPage = document.querySelector('[data-course-edit-page]');

    if (!courseEditPage) {
        return;
    }

    initializeValidityIssueDialogs(courseEditPage);
    initializeCreateModuleDialog(courseEditPage);
    initializeDeleteCourseDialog(courseEditPage);
    initializeDuplicateStructureDialog(courseEditPage);
    initializeDeleteModuleDialogs(courseEditPage);
    initializeModuleSorting(courseEditPage);
    initializeSatisfactionSurveyFields(courseEditPage);
    initializeCourseRiskRequirements(courseEditPage);
    initializeCourseClasses(courseEditPage);
    initializeTeacherAssignmentsTable(courseEditPage);
    initializeTutorAssignmentsTable(courseEditPage);
    initializeEnrollmentsTable(courseEditPage);
});

function initializeValidityIssueDialogs(scope) {
    const containers = scope.querySelectorAll('[data-validity-details]');

    if (containers.length === 0) {
        return;
    }

    containers.forEach((container) => {
        const modalSelector = container.dataset.validityModalTarget;
        const modal = (modalSelector ? scope.querySelector(modalSelector) : null)
            ?? container.querySelector('[data-validity-details-modal]')
            ?? container.nextElementSibling;

        if (!(modal instanceof HTMLDialogElement)) {
            return;
        }

        container.querySelectorAll('[data-open-validity-details-modal]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                modal.showModal();
            });
        });

        modal.querySelectorAll('[data-close-validity-details-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                modal.close();
            });
        });
    });
}

function initializeCourseRiskRequirements(courseEditPage) {
    const container = courseEditPage.querySelector('[data-course-risk-requirements]');

    if (!container) {
        return;
    }

    const list = container.querySelector('[data-course-risk-requirements-list]');
    const emptyState = container.querySelector('[data-course-risk-requirements-empty]');
    const hiddenInputsContainer = container.querySelector('[data-course-risk-requirements-hidden-inputs]');
    const openSelectionModalButton = container.querySelector('[data-open-risk-requirement-selection-modal]');
    const selectionModal = container.querySelector('[data-course-risk-requirement-selection-modal]');
    const selectionTableBody = container.querySelector('[data-course-risk-requirement-selection-tbody]');
    const selectionEmptyState = container.querySelector('[data-course-risk-requirement-selection-empty]');
    const closeSelectionModalButtons = container.querySelectorAll('[data-close-risk-requirement-selection-modal]');
    const validityModal = container.querySelector('[data-course-risk-requirement-validity-modal]');
    const validityModalTitle = container.querySelector('[data-course-risk-requirement-validity-modal-title]');
    const validityModalDescription = container.querySelector('[data-course-risk-requirement-validity-modal-description]');
    const validityOptions = Array.from(container.querySelectorAll('[data-course-risk-requirement-validity-option]'));
    const integrativeFields = container.querySelector('[data-course-risk-requirement-integrative-fields]');
    const integrativeOptions = Array.from(container.querySelectorAll('[data-integrative-start-level-option]'));
    const closeValidityModalButtons = container.querySelectorAll('[data-close-risk-requirement-validity-modal]');
    const confirmValidityButton = container.querySelector('[data-confirm-risk-requirement-validity]');
    const deleteModal = container.querySelector('[data-course-risk-requirement-delete-modal]');
    const deleteModalDescription = container.querySelector('[data-course-risk-requirement-delete-modal-description]');
    const closeDeleteModalButtons = container.querySelectorAll('[data-close-risk-requirement-delete-modal]');
    const confirmDeleteButton = container.querySelector('[data-confirm-risk-requirement-delete]');
    const allRequirementsScript = container.querySelector('[data-course-risk-requirements-all]');
    const selectedRequirementsScript = container.querySelector('[data-course-risk-requirements-selected]');

    if (!list || !emptyState || !hiddenInputsContainer || !selectionModal || !selectionTableBody || !selectionEmptyState || !validityModal || !validityModalTitle || !validityModalDescription || validityOptions.length === 0 || !integrativeFields || integrativeOptions.length === 0 || !confirmValidityButton || !deleteModal || !deleteModalDescription || !confirmDeleteButton || !allRequirementsScript || !selectedRequirementsScript) {
        return;
    }

    const allRequirements = JSON.parse(allRequirementsScript.textContent || '[]');
    const initialAssociations = JSON.parse(selectedRequirementsScript.textContent || '[]');
    const validityTypeLabels = Object.fromEntries(
        validityOptions.map((option) => [option.value, option.closest('label')?.querySelector('.label-text')?.textContent.trim() || option.value]),
    );

    const state = {
        associations: Array.isArray(initialAssociations) ? [...initialAssociations] : [],
        pendingRequirement: null,
        pendingAction: null,
    };

    const escapeHtml = (value) => String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const getAssociationIndex = (requirementId) => state.associations.findIndex(
        (association) => Number(association.id) === Number(requirementId),
    );

    const selectedValidityTypes = () => validityOptions
        .filter((option) => option.checked)
        .map((option) => option.value);

    const hasIntegrativeValidity = (types) => Array.isArray(types) && types.includes('integrative');

    const setSelectedValidityTypes = (types = []) => {
        validityOptions.forEach((option) => {
            option.checked = Array.isArray(types) && types.includes(option.value);
        });
    };

    const syncIntegrativeFields = (requirement = null, selectedLevels = []) => {
        const isIntegrative = hasIntegrativeValidity(selectedValidityTypes());
        const finalRiskLevel = requirement?.single_risk_level || null;
        const riskOrder = { low: 1, medium: 2, high: 3 };

        integrativeFields.classList.toggle('hidden', !isIntegrative);

        integrativeOptions.forEach((option) => {
            const shouldDisable = !isIntegrative || (finalRiskLevel !== null && (riskOrder[option.value] ?? 0) >= (riskOrder[finalRiskLevel] ?? 0));
            option.disabled = shouldDisable;
            option.checked = selectedLevels.includes(option.value) && !shouldDisable;
        });
    };

    const syncHiddenInputs = () => {
        hiddenInputsContainer.innerHTML = '';

        state.associations.forEach((association) => {
            const associationValidityTypes = Array.isArray(association.course_validity_types)
                ? association.course_validity_types
                : [];
            const requirementIdInput = document.createElement('input');
            requirementIdInput.type = 'hidden';
            requirementIdInput.name = 'risk_based_requirement_ids[]';
            requirementIdInput.value = String(association.id);
            hiddenInputsContainer.appendChild(requirementIdInput);

            associationValidityTypes.forEach((validityType) => {
                const validityTypeInput = document.createElement('input');
                validityTypeInput.type = 'hidden';
                validityTypeInput.name = `risk_based_requirement_validity_types[${association.id}][]`;
                validityTypeInput.value = validityType;
                hiddenInputsContainer.appendChild(validityTypeInput);
            });

            if (hasIntegrativeValidity(associationValidityTypes) && Array.isArray(association.integrative_start_risk_levels)) {
                association.integrative_start_risk_levels.forEach((riskLevel) => {
                    const integrativeInput = document.createElement('input');
                    integrativeInput.type = 'hidden';
                    integrativeInput.name = `risk_based_requirement_integrative_start_levels[${association.id}][]`;
                    integrativeInput.value = riskLevel;
                    hiddenInputsContainer.appendChild(integrativeInput);
                });
            }
        });
    };

    const renderAssociations = () => {
        list.innerHTML = '';
        emptyState.classList.toggle('hidden', state.associations.length > 0);

        state.associations.forEach((association) => {
            const associationValidityTypes = Array.isArray(association.course_validity_types)
                ? association.course_validity_types
                : [];
            const validityBadges = associationValidityTypes
                .map((validityType) => `<span class="badge badge-outline badge-sm h-fit">${escapeHtml(validityTypeLabels[validityType] || validityType)}</span>`)
                .join(' ');
            const item = document.createElement('div');
            item.className = 'rounded-box border border-base-300 bg-base-100 p-4';
            item.innerHTML = `
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-1">
                        <p class="font-medium text-base-content">${escapeHtml(association.name)}</p>
                        ${association.description ? `<p class="text-sm text-base-content/70">${escapeHtml(association.description)}</p>` : ''}
                        <div class="flex flex-wrap gap-2">${validityBadges}</div>
                        ${hasIntegrativeValidity(associationValidityTypes) && Array.isArray(association.integrative_start_risk_levels) && association.integrative_start_risk_levels.length > 0
                            ? `<p class="text-sm text-base-content/70">Livelli iniziali: ${escapeHtml(association.integrative_start_risk_levels.join(', '))}</p>`
                            : ''}
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-action="edit">${escapeHtml('Modifica validità')}</button>
                        <button type="button" class="btn btn-accent btn-outline btn-sm" data-action="delete">${escapeHtml('Elimina')}</button>
                    </div>
                </div>
            `;

            item.querySelector('[data-action="edit"]').addEventListener('click', () => {
                state.pendingRequirement = association;
                state.pendingAction = 'edit';
                validityModalTitle.textContent = 'Modifica validità del corso';
                validityModalDescription.textContent = `Imposta come il corso vale per il requisito "${association.name}".`;
                setSelectedValidityTypes(association.course_validity_types || []);
                syncIntegrativeFields(association, association.integrative_start_risk_levels || []);
                validityModal.showModal();
            });

            item.querySelector('[data-action="delete"]').addEventListener('click', () => {
                state.pendingRequirement = association;
                deleteModalDescription.textContent = `Vuoi rimuovere l'associazione del requisito "${association.name}" da questo corso?`;
                deleteModal.showModal();
            });

            list.appendChild(item);
        });

        syncHiddenInputs();
    };

    const renderSelectionOptions = () => {
        selectionTableBody.innerHTML = '';

        const selectedIds = new Set(state.associations.map((association) => Number(association.id)));
        const availableRequirements = allRequirements.filter(
            (requirement) => !selectedIds.has(Number(requirement.id)),
        );

        selectionEmptyState.classList.toggle('hidden', availableRequirements.length > 0);

        availableRequirements.forEach((requirement) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="font-medium">${escapeHtml(requirement.name)}</td>
                <td class="text-sm text-base-content/70">${escapeHtml(requirement.description || '-')}</td>
                <td class="text-right">
                    <button type="button" class="btn btn-primary btn-sm" data-action="add">Aggiungi</button>
                </td>
            `;

            row.querySelector('[data-action="add"]').addEventListener('click', () => {
                state.pendingRequirement = requirement;
                state.pendingAction = 'create';
                validityModalTitle.textContent = 'Imposta validità del corso';
                validityModalDescription.textContent = `Imposta come il corso vale per il requisito "${requirement.name}".`;
                setSelectedValidityTypes([]);
                syncIntegrativeFields(requirement, []);
                selectionModal.close();
                validityModal.showModal();
            });

            selectionTableBody.appendChild(row);
        });
    };

    confirmValidityButton.addEventListener('click', () => {
        if (!state.pendingRequirement) {
            return;
        }

        const selectedTypes = selectedValidityTypes();
        const selectedIntegrativeLevels = integrativeOptions
            .filter((option) => option.checked && !option.disabled)
            .map((option) => option.value);

        if (selectedTypes.length === 0) {
            window.alert('Seleziona almeno una tipologia di validitÃ  per il requisito.');

            return;
        }

        if (hasIntegrativeValidity(selectedTypes) && selectedIntegrativeLevels.length === 0) {
            window.alert('Seleziona almeno un livello di partenza per il corso integrativo.');

            return;
        }

        const payload = {
            ...state.pendingRequirement,
            course_validity_types: selectedTypes,
            integrative_start_risk_levels: hasIntegrativeValidity(selectedTypes) ? selectedIntegrativeLevels : [],
        };
        const existingIndex = getAssociationIndex(payload.id);

        if (existingIndex >= 0) {
            state.associations.splice(existingIndex, 1, payload);
        } else {
            state.associations.push(payload);
            state.associations.sort((left, right) => left.name.localeCompare(right.name, 'it'));
        }

        state.pendingRequirement = null;
        state.pendingAction = null;
        setSelectedValidityTypes([]);
        syncIntegrativeFields(null, []);
        validityModal.close();
        renderAssociations();
        renderSelectionOptions();
    });

    confirmDeleteButton.addEventListener('click', () => {
        if (!state.pendingRequirement) {
            return;
        }

        state.associations = state.associations.filter(
            (association) => Number(association.id) !== Number(state.pendingRequirement.id),
        );
        state.pendingRequirement = null;
        deleteModal.close();
        renderAssociations();
        renderSelectionOptions();
    });

    if (openSelectionModalButton) {
        openSelectionModalButton.addEventListener('click', () => {
            renderSelectionOptions();
            selectionModal.showModal();
        });
    }

    closeSelectionModalButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectionModal.close();
        });
    });

    closeValidityModalButtons.forEach((button) => {
        button.addEventListener('click', () => {
            state.pendingRequirement = null;
            state.pendingAction = null;
            setSelectedValidityTypes([]);
            syncIntegrativeFields(null, []);
            validityModal.close();
        });
    });

    closeDeleteModalButtons.forEach((button) => {
        button.addEventListener('click', () => {
            state.pendingRequirement = null;
            deleteModal.close();
        });
    });

    validityOptions.forEach((option) => {
        option.addEventListener('change', () => {
            syncIntegrativeFields(
                state.pendingRequirement,
                state.pendingRequirement?.integrative_start_risk_levels || [],
            );
        });
    });

    renderAssociations();
    renderSelectionOptions();
}

function initializeCourseClasses(courseEditPage) {
    const container = courseEditPage.querySelector('[data-course-classes]');

    if (!container) {
        return;
    }

    const indexUrl = container.dataset.classesIndexUrl;
    const storeUrl = container.dataset.classesStoreUrl;
    const searchUsersUrl = container.dataset.classesSearchUsersUrl;
    const searchTeachersUrl = container.dataset.classesSearchTeachersUrl;
    const initialScript = container.querySelector('[data-course-classes-initial]');
    const tbody = container.querySelector('[data-course-classes-tbody]');
    const emptyState = container.querySelector('[data-course-classes-empty]');
    const tableContainer = container.querySelector('[data-course-classes-table-container]');
    const tableLoader = container.querySelector('[data-course-classes-loader]');
    const rowTemplate = container.querySelector('[data-course-class-row-template]');
    const scheduleTemplate = container.querySelector('[data-course-class-schedule-template]');
    const openClassModalButton = container.querySelector('[data-open-course-class-modal]');
    const classModal = container.querySelector('[data-course-class-modal]');
    const closeClassModalButton = container.querySelector('[data-close-course-class-modal]');
    const classForm = container.querySelector('[data-course-class-form]');
    const classModalTitle = container.querySelector('[data-course-class-modal-title]');
    const classFormError = container.querySelector('[data-course-class-form-error]');
    const schedulesContainer = container.querySelector('[data-course-class-schedules]');
    const addScheduleButton = container.querySelector('[data-add-course-class-schedule]');
    const peopleModal = container.querySelector('[data-course-class-people-modal]');
    const closePeopleModalButton = container.querySelector('[data-close-course-class-people-modal]');
    const peopleTitle = container.querySelector('[data-course-class-people-title]');
    const peopleSubtitle = container.querySelector('[data-course-class-people-subtitle]');
    const peopleCount = container.querySelector('[data-course-class-people-count]');
    const peopleSearch = container.querySelector('[data-course-class-people-search]');
    const peopleSearchButton = container.querySelector('[data-course-class-people-search-button]');
    const peopleResults = container.querySelector('[data-course-class-people-results]');
    const peopleConfirmButton = container.querySelector('[data-course-class-people-confirm]');
    const peopleAssigned = container.querySelector('[data-course-class-people-assigned]');
    const peopleConfirmRemovalButton = container.querySelector('[data-course-class-people-confirm-removal]');
    const peopleError = container.querySelector('[data-course-class-people-error]');

    if (!indexUrl || !storeUrl || !tbody || !emptyState || !tableContainer || !tableLoader || !rowTemplate || !scheduleTemplate || !openClassModalButton || !classModal || !closeClassModalButton || !classForm || !classModalTitle || !classFormError || !schedulesContainer || !addScheduleButton || !peopleModal || !closePeopleModalButton || !peopleTitle || !peopleSubtitle || !peopleCount || !peopleSearch || !peopleSearchButton || !peopleResults || !peopleConfirmButton || !peopleAssigned || !peopleConfirmRemovalButton || !peopleError) {
        return;
    }

    const defaultModuleId = classForm.elements.namedItem('module_id')?.value || '';

    const state = {
        classes: initialScript ? JSON.parse(initialScript.textContent || '[]') : [],
        editingClass: null,
        peopleMode: null,
        peopleClass: null,
        peopleResultsData: [],
        pendingPeople: [],
        pendingRemovals: [],
        peopleSearching: false,
        peopleMutating: false,
        reloadingClasses: false,
    };

    const showError = (element, message) => {
        element.textContent = message || '';
        element.classList.toggle('hidden', !message);
    };
    const escapeHtml = (value) => String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const getClassById = (classId) => state.classes.find((courseClass) => Number(courseClass.id) === Number(classId));

    const replaceClass = (updatedClass) => {
        state.classes = state.classes.map((courseClass) => {
            if (Number(courseClass.id) === Number(updatedClass.id)) {
                return updatedClass;
            }

            return courseClass;
        });

        renderClasses();
        state.peopleClass = getClassById(updatedClass.id) || updatedClass;
    };

    const updatePeopleActionButtons = () => {
        const addCount = state.pendingPeople.length;
        const removeCount = state.pendingRemovals.length;

        peopleConfirmButton.disabled = state.peopleSearching || state.peopleMutating || addCount === 0;
        peopleConfirmRemovalButton.disabled = state.peopleSearching || state.peopleMutating || removeCount === 0;
        peopleConfirmButton.textContent = addCount > 0
            ? `Conferma selezione (${addCount})`
            : 'Conferma selezione';
        peopleConfirmRemovalButton.textContent = removeCount > 0
            ? `Conferma rimozione (${removeCount})`
            : 'Conferma rimozione';
    };

    const isUserMode = () => state.peopleMode === 'users';

    const getAssignedPeople = () => (isUserMode() ? (state.peopleClass?.users || []) : (state.peopleClass?.teachers || []));

    const getPendingIds = () => new Set(state.pendingPeople.map((person) => Number(person.id)));

    const getPendingRemovalIds = () => new Set(state.pendingRemovals.map((person) => Number(person.assignment_id)));

    const getEffectiveAssignedCount = () => {
        if (!isUserMode()) {
            return getAssignedPeople().length - state.pendingRemovals.length;
        }

        return Number(state.peopleClass?.users_count || 0) + state.pendingPeople.length - state.pendingRemovals.length;
    };

    const syncPeopleLoadingState = () => {
        const loading = state.peopleSearching || state.peopleMutating;

        peopleSearch.disabled = loading;
        peopleSearchButton.disabled = loading;
        peopleResults.classList.toggle('pointer-events-none', loading);
        peopleResults.classList.toggle('opacity-70', loading);
        peopleAssigned.classList.toggle('pointer-events-none', loading);
        peopleAssigned.classList.toggle('opacity-70', loading);
        updatePeopleActionButtons();
    };

    const syncClassesLoadingState = () => {
        toggleAsyncTableLoading({ container: tableContainer, loader: tableLoader }, state.reloadingClasses);
    };

    const refreshClasses = async () => {
        state.reloadingClasses = true;
        syncClassesLoadingState();

        try {
            const response = await window.axios.get(indexUrl, { headers: { Accept: 'application/json' } });
            state.classes = response.data.data || [];
            renderClasses();
        } finally {
            state.reloadingClasses = false;
            syncClassesLoadingState();
        }
    };

    const renderClasses = () => {
        tbody.innerHTML = '';
        emptyState.classList.toggle('hidden', state.classes.length > 0);

        state.classes.forEach((courseClass) => {
            const row = rowTemplate.content.firstElementChild.cloneNode(true);
            row.dataset.classId = courseClass.id;
            row.querySelector('[data-class-module]').textContent = courseClass.module_title || '-';
            row.querySelector('[data-class-name]').textContent = courseClass.name;
            row.querySelector('[data-class-starts]').textContent = courseClass.starts_at_label || '-';
            row.querySelector('[data-class-ends]').textContent = courseClass.ends_at_label || '-';
            row.querySelector('[data-class-schedules-count]').textContent = courseClass.schedules_count || 0;
            row.querySelector('[data-class-users]').textContent = `${courseClass.users_count}/30`;
            row.querySelector('[data-class-teachers]').textContent = courseClass.teachers_count;
            row.querySelector('[data-edit-class]').addEventListener('click', () => openClassForm(courseClass));
            row.querySelector('[data-manage-class-users]').addEventListener('click', () => openPeopleModal(courseClass, 'users'));
            row.querySelector('[data-manage-class-teachers]').addEventListener('click', () => openPeopleModal(courseClass, 'teachers'));
            row.querySelector('[data-delete-class]').addEventListener('click', () => deleteClass(courseClass));
            tbody.appendChild(row);
        });
    };

    const hydrateScheduleInputNames = () => {
        schedulesContainer.querySelectorAll('[data-course-class-schedule-row]').forEach((row, index) => {
            row.querySelector('[data-schedule-starts-date]').name = `schedules[${index}][starts_at_date]`;
            row.querySelector('[data-schedule-starts-time]').name = `schedules[${index}][starts_at_time]`;
            row.querySelector('[data-schedule-ends-date]').name = `schedules[${index}][ends_at_date]`;
            row.querySelector('[data-schedule-ends-time]').name = `schedules[${index}][ends_at_time]`;
        });
    };

    const appendScheduleRow = (schedule = null) => {
        const row = scheduleTemplate.content.firstElementChild.cloneNode(true);
        row.querySelector('[data-schedule-starts-date]').value = schedule?.starts_at_date || '';
        row.querySelector('[data-schedule-starts-time]').value = schedule?.starts_at_time || '';
        row.querySelector('[data-schedule-ends-date]').value = schedule?.ends_at_date || '';
        row.querySelector('[data-schedule-ends-time]').value = schedule?.ends_at_time || '';
        row.querySelector('[data-remove-course-class-schedule]').addEventListener('click', () => {
            if (schedulesContainer.children.length <= 1) {
                return;
            }

            row.remove();
            hydrateScheduleInputNames();
        });
        schedulesContainer.appendChild(row);
        hydrateScheduleInputNames();
    };

    const openClassForm = (courseClass = null) => {
        state.editingClass = courseClass;
        classModalTitle.textContent = courseClass ? 'Modifica classe' : 'Nuova classe';
        classForm.module_id.value = courseClass?.module_id || defaultModuleId;
        classForm.name.value = courseClass?.name || '';
        schedulesContainer.innerHTML = '';
        (courseClass?.schedules || []).forEach((schedule) => appendScheduleRow(schedule));

        if (schedulesContainer.children.length === 0) {
            appendScheduleRow();
        }

        showError(classFormError, '');
        classModal.showModal();
    };

    const deleteClass = async (courseClass) => {
        if (!window.confirm(`Eliminare la classe "${courseClass.name}"?`)) {
            return;
        }

        await window.axios.delete(courseClass.routes.delete, { headers: { Accept: 'application/json' } });
        await refreshClasses();
    };

    const submitClassForm = async (event) => {
        event.preventDefault();
        showError(classFormError, '');

        const formData = new FormData(classForm);
        const payload = {
            module_id: formData.get('module_id'),
            name: formData.get('name'),
            schedules: Array.from(schedulesContainer.querySelectorAll('[data-course-class-schedule-row]')).map((row) => ({
                starts_at_date: row.querySelector('[data-schedule-starts-date]').value,
                starts_at_time: row.querySelector('[data-schedule-starts-time]').value,
                ends_at_date: row.querySelector('[data-schedule-ends-date]').value,
                ends_at_time: row.querySelector('[data-schedule-ends-time]').value,
            })),
        };
        const url = state.editingClass?.routes.update || storeUrl;
        const submitButton = classForm.querySelector('button[type="submit"]');

        try {
            setButtonLoading(submitButton, true, { loadingText: 'Salvataggio...' });

            if (state.editingClass) {
                await window.axios.put(url, payload, { headers: { Accept: 'application/json' } });
            } else {
                await window.axios.post(url, payload, { headers: { Accept: 'application/json' } });
            }

            classModal.close();
            await refreshClasses();
        } catch (error) {
            showError(classFormError, error.response?.data?.message || 'Errore durante il salvataggio della classe.');
        } finally {
            setButtonLoading(submitButton, false);
        }
    };

    const openPeopleModal = (courseClass, mode) => {
        state.peopleClass = courseClass;
        state.peopleMode = mode;
        state.peopleResultsData = [];
        state.pendingPeople = [];
        state.pendingRemovals = [];
        peopleSearch.value = '';
        peopleResults.innerHTML = '';
        showError(peopleError, '');
        renderAssignedPeople();
        peopleTitle.textContent = mode === 'users' ? `Utenti - ${courseClass.name}` : `Docenti - ${courseClass.name}`;
        peopleSubtitle.textContent = mode === 'users'
            ? 'Aggiungi utenti standard alla classe. Il limite massimo è 30 utenti.'
            : 'Aggiungi docenti alla classe. Non è previsto un limite numerico.';
        syncPeopleLoadingState();
        peopleModal.showModal();
    };

    const renderAssignedPeople = () => {
        const courseClass = state.peopleClass;
        const mode = state.peopleMode;
        const assigned = mode === 'users' ? (courseClass?.users || []) : (courseClass?.teachers || []);
        peopleAssigned.innerHTML = '';
        peopleCount.textContent = mode === 'users' ? `${assigned.length}/30` : assigned.length;

        if (assigned.length === 0) {
            peopleAssigned.innerHTML = '<tr><td class="text-sm text-base-content/60">Nessuna assegnazione presente.</td></tr>';
            return;
        }

        assigned.forEach((person) => {
            const isPendingRemoval = state.pendingRemovals.some((pendingPerson) => Number(pendingPerson.assignment_id) === Number(person.assignment_id));
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="font-medium">${escapeHtml(person.full_name || '-')}</div>
                    <div class="text-xs text-base-content/60">${escapeHtml(person.email || '')}</div>
                </td>
                <td class="text-right"><button type="button" class="btn btn-accent btn-sm" ${state.peopleMutating ? 'disabled' : ''}>${isPendingRemoval ? 'Da rimuovere' : 'Seleziona'}</button></td>
            `;
            row.querySelector('button').addEventListener('click', () => togglePendingRemoval(person));
            peopleAssigned.appendChild(row);
        });
    };

    const togglePendingPerson = (person) => {
        const personId = Number(person.id);
        const isPending = state.pendingPeople.some((pendingPerson) => Number(pendingPerson.id) === personId);

        if (isPending) {
            state.pendingPeople = state.pendingPeople.filter((pendingPerson) => Number(pendingPerson.id) !== personId);
        } else {
            state.pendingPeople = [...state.pendingPeople, person];
        }

        updatePeopleActionButtons();
        renderPeopleResults(state.peopleResultsData);
    };

    const togglePendingRemoval = (person) => {
        const assignmentId = Number(person.assignment_id);
        const isPending = state.pendingRemovals.some((pendingPerson) => Number(pendingPerson.assignment_id) === assignmentId);

        if (isPending) {
            state.pendingRemovals = state.pendingRemovals.filter((pendingPerson) => Number(pendingPerson.assignment_id) !== assignmentId);
        } else {
            state.pendingRemovals = [...state.pendingRemovals, person];
        }

        renderAssignedPeople();
        updatePeopleActionButtons();
        renderPeopleResults(state.peopleResultsData);
    };

    const searchPeople = async () => {
        const mode = state.peopleMode;
        const url = mode === 'users' ? searchUsersUrl : searchTeachersUrl;

        if (!url) {
            return;
        }

        state.peopleSearching = true;
        syncPeopleLoadingState();

        try {
            const response = await window.axios.get(url, {
                params: { search: peopleSearch.value.trim() },
                headers: { Accept: 'application/json' },
            });

            state.peopleResultsData = response.data.data || [];
            renderPeopleResults(state.peopleResultsData);
        } catch (error) {
            state.peopleResultsData = [];
            renderPeopleResults(state.peopleResultsData);
            showError(peopleError, error.response?.data?.message || 'Errore durante la ricerca utenti.');
        } finally {
            state.peopleSearching = false;
            syncPeopleLoadingState();
        }
    };

    const renderPeopleResults = (people) => {
        peopleResults.innerHTML = '';

        if (people.length === 0) {
            peopleResults.innerHTML = '<tr><td class="text-sm text-base-content/60">Nessun risultato.</td></tr>';
            return;
        }

        const assignedIds = new Set(getAssignedPeople().map((person) => Number(person.id)));
        const pendingIds = getPendingIds();
        const pendingRemovalIds = getPendingRemovalIds();
        const isAtCapacity = isUserMode() && getEffectiveAssignedCount() >= 30;

        people.forEach((person) => {
            const alreadyAssigned = assignedIds.has(Number(person.id)) && !pendingRemovalIds.has(Number(person.assignment_id));
            const isPending = pendingIds.has(Number(person.id));
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="font-medium">${escapeHtml(person.full_name || '-')}</div>
                    <div class="text-xs text-base-content/60">${escapeHtml(person.email || '')}</div>
                </td>
                <td class="text-right"><button type="button" class="btn btn-primary btn-sm" ${alreadyAssigned || (!isPending && isAtCapacity) || state.peopleMutating ? 'disabled' : ''}>${isPending ? 'Selezionato' : 'Seleziona'}</button></td>
            `;
            row.querySelector('button').addEventListener('click', () => togglePendingPerson(person));
            peopleResults.appendChild(row);
        });
    };

    const confirmPendingPeople = async () => {
        if (state.pendingPeople.length === 0) {
            return;
        }

        showError(peopleError, '');
        const mode = state.peopleMode;
        const payload = mode === 'users'
            ? { user_ids: state.pendingPeople.map((person) => Number(person.id)) }
            : { teacher_ids: state.pendingPeople.map((person) => Number(person.id)) };
        const url = mode === 'users' ? state.peopleClass.routes.users_store : state.peopleClass.routes.teachers_store;

        try {
            state.peopleMutating = true;
            syncPeopleLoadingState();
            setButtonLoading(peopleConfirmButton, true, { loadingText: 'Salvataggio...' });

            const response = await window.axios.post(url, payload, { headers: { Accept: 'application/json' } });

            replaceClass(response.data.data);
            state.pendingPeople = [];
            renderAssignedPeople();
            updatePeopleActionButtons();
            renderPeopleResults(state.peopleResultsData);
            peopleModal.close();
        } catch (error) {
            showError(peopleError, error.response?.data?.message || 'Errore durante l\'assegnazione.');
        } finally {
            state.peopleMutating = false;
            setButtonLoading(peopleConfirmButton, false);
            syncPeopleLoadingState();
        }
    };

    const confirmPendingRemovals = async () => {
        if (state.pendingRemovals.length === 0) {
            return;
        }

        showError(peopleError, '');
        const url = isUserMode() ? state.peopleClass.routes.users_destroy_many : state.peopleClass.routes.teachers_destroy_many;

        try {
            state.peopleMutating = true;
            syncPeopleLoadingState();
            setButtonLoading(peopleConfirmRemovalButton, true, { loadingText: 'Salvataggio...' });

            const response = await window.axios.delete(url, {
                data: { assignment_ids: state.pendingRemovals.map((person) => Number(person.assignment_id)) },
                headers: { Accept: 'application/json' },
            });

            replaceClass(response.data.data);
            state.pendingRemovals = [];
            renderAssignedPeople();
            updatePeopleActionButtons();
            renderPeopleResults(state.peopleResultsData);
            peopleModal.close();
        } catch (error) {
            showError(peopleError, error.response?.data?.message || 'Errore durante la rimozione.');
        } finally {
            state.peopleMutating = false;
            setButtonLoading(peopleConfirmRemovalButton, false);
            syncPeopleLoadingState();
        }
    };

    openClassModalButton.addEventListener('click', () => openClassForm());
    closeClassModalButton.addEventListener('click', () => classModal.close());
    addScheduleButton.addEventListener('click', () => appendScheduleRow());
    closePeopleModalButton.addEventListener('click', () => peopleModal.close());
    peopleModal.addEventListener('cancel', (event) => {
        event.preventDefault();
    });
    peopleModal.addEventListener('click', (event) => {
        if (event.target === peopleModal) {
            event.preventDefault();
        }
    });
    classForm.addEventListener('submit', submitClassForm);
    peopleSearchButton.addEventListener('click', searchPeople);
    peopleConfirmButton.addEventListener('click', confirmPendingPeople);
    peopleConfirmRemovalButton.addEventListener('click', confirmPendingRemovals);
    peopleSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchPeople();
        }
    });

    renderClasses();
}

function initializeCreateModuleDialog(courseEditPage) {
    if (courseEditPage.dataset.courseIsPublished === 'true') {
        return;
    }

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

function initializeSatisfactionSurveyFields(courseEditPage) {
    const enabledCheckbox = courseEditPage.querySelector('[data-satisfaction-enabled]');
    const requiredCheckbox = courseEditPage.querySelector('[data-satisfaction-required]');

    if (!enabledCheckbox || !requiredCheckbox) {
        return;
    }

    const syncState = () => {
        requiredCheckbox.disabled = !enabledCheckbox.checked;

        if (!enabledCheckbox.checked) {
            requiredCheckbox.checked = false;
        }
    };

    enabledCheckbox.addEventListener('change', syncState);
    syncState();
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

function initializeDuplicateStructureDialog(courseEditPage) {
    const duplicateStructureModal = courseEditPage.querySelector('#duplicate-structure-modal');
    const openDuplicateStructureModalButton = courseEditPage.querySelector('[data-open-duplicate-structure-modal]');

    if (!duplicateStructureModal || !openDuplicateStructureModalButton) {
        return;
    }

    openDuplicateStructureModalButton.addEventListener('click', () => {
        duplicateStructureModal.showModal();
    });

    if (duplicateStructureModal.querySelector('.input-error') !== null) {
        duplicateStructureModal.showModal();
    }
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
    const isLockedItem = (item) => item?.dataset.moduleType === 'satisfaction_quiz';
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

        if (!targetItem || targetItem === draggedItem || isLockedItem(targetItem)) {
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
            if (isSaving || isLockedItem(item)) {
                draggedItem = null;
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

        if (isLockedItem(item)) {
            item.classList.add('opacity-90');
            item.querySelector('.cursor-move')?.classList.add('opacity-40', 'cursor-not-allowed');
        }
    });
}

function initializeEnrollmentsTable(courseEditPage) {
    const container = courseEditPage.querySelector('[data-enrollments-table]');

    if (!container) {
        return;
    }

    const apiUrl = container.dataset.enrollmentsApiUrl;
    const searchUsersApiUrl = container.dataset.enrollmentsSearchUsersApiUrl;
    const storeApiUrl = container.dataset.enrollmentsStoreApiUrl;
    const tbody = container.querySelector('[data-enrollments-tbody]');
    const template = container.querySelector('[data-enrollment-row-template]');
    const emptyState = container.querySelector('[data-enrollments-empty]');
    const tableContainer = container.querySelector('[data-enrollments-table-container]');
    const tableLoader = container.querySelector('[data-enrollments-loader]');
    const searchInput = container.querySelector('[data-enrollments-search]');
    const searchButton = container.querySelector('[data-enrollments-search-button]');
    const showTrashedCheckbox = container.querySelector('[data-enrollments-show-trashed]');
    const paginationContainer = container.querySelector('[data-enrollments-pagination]');
    const summaryElement = container.querySelector('[data-enrollments-summary]');
    const sortButtons = Array.from(container.querySelectorAll('[data-sort-key]'));
    const sortIndicators = Array.from(new Set(Array.from(container.querySelectorAll('[data-sort-indicator]')).map((indicator) => indicator.dataset.sortIndicator)));
    const openCreateEnrollmentModalButton = container.querySelector('[data-open-iscritto-modal]');
    const createEnrollmentModal = container.querySelector('[data-create-enrollment-modal]');
    const closeCreateEnrollmentModalButton = container.querySelector('[data-close-create-enrollment-modal]');
    const userSearchInput = container.querySelector('[data-enrollment-user-search]');
    const userSearchButton = container.querySelector('[data-enrollment-user-search-button]');
    const userResultsBody = container.querySelector('[data-enrollment-user-results]');
    const userResultsEmptyState = container.querySelector('[data-enrollment-user-results-empty]');
    const userRowTemplate = container.querySelector('[data-enrollment-user-row-template]');
    const confirmEnrollmentModal = container.querySelector('[data-confirm-enrollment-modal]');
    const confirmEnrollmentMessage = container.querySelector('[data-confirm-enrollment-message]');
    const confirmEnrollmentSubmitButton = container.querySelector('[data-confirm-enrollment-submit]');

    if (!apiUrl || !searchUsersApiUrl || !storeApiUrl || !tbody || !template || !emptyState || !tableContainer || !tableLoader || !searchInput || !searchButton || !showTrashedCheckbox || !paginationContainer || !summaryElement || !openCreateEnrollmentModalButton || !createEnrollmentModal || !closeCreateEnrollmentModalButton || !userSearchInput || !userSearchButton || !userResultsBody || !userResultsEmptyState || !userRowTemplate || !confirmEnrollmentModal || !confirmEnrollmentMessage || !confirmEnrollmentSubmitButton) {
        return;
    }

    const state = {
        page: 1,
        search: '',
        showTrashed: false,
        sort: 'surname',
        direction: 'asc',
        loading: false,
        selectedUserForEnrollment: null,
        confirmAction: 'create',
        restoreUrl: null,
    };

    const updateSortIndicators = () => {
        sortIndicators.forEach((key) => {
            const icons = container.querySelectorAll(`[data-sort-indicator="${key}"]`);

            icons.forEach((icon) => {
                const iconType = icon.dataset.sortIcon;
                let shouldShow = false;

                if (state.sort !== key) {
                    shouldShow = iconType === 'none';
                } else if (state.direction === 'asc') {
                    shouldShow = iconType === 'asc';
                } else if (state.direction === 'desc') {
                    shouldShow = iconType === 'desc';
                }

                icon.classList.toggle('hidden', !shouldShow);
            });
        });
    };

    const buildQueryString = () => {
        const params = new URLSearchParams();

        params.set('page', String(state.page));
        params.set('show_trashed', state.showTrashed ? '1' : '0');

        if (state.search !== '') {
            params.set('search', state.search);
        }

        if (state.sort !== null && state.direction !== null) {
            params.set('sort', state.sort);
            params.set('direction', state.direction);
        }

        return params.toString();
    };

    const setLoadingState = (loading) => {
        state.loading = loading;
        toggleAsyncTableLoading({ scope: container, container: tableContainer, loader: tableLoader }, loading);
    };

    const cycleSortDirection = (currentDirection) => {
        if (currentDirection === null) {
            return 'asc';
        }

        if (currentDirection === 'asc') {
            return 'desc';
        }

        return null;
    };

    const renderPagination = (meta) => {
        paginationContainer.innerHTML = '';

        if (meta.last_page <= 1) {
            return;
        }

        const createPageButton = (label, page, disabled = false, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `join-item btn btn-sm ${active ? 'btn-primary' : 'btn-ghost'}`;
            button.textContent = label;
            button.disabled = disabled;

            if (!disabled) {
                button.addEventListener('click', () => {
                    state.page = page;
                    loadEnrollments();
                });
            }

            return button;
        };

        paginationContainer.appendChild(createPageButton('«', Math.max(meta.current_page - 1, 1), meta.current_page <= 1));

        const pagesToRender = new Set([
            1,
            meta.last_page,
            Math.max(meta.current_page - 1, 1),
            meta.current_page,
            Math.min(meta.current_page + 1, meta.last_page),
        ]);

        Array.from(pagesToRender)
            .sort((a, b) => a - b)
            .forEach((page) => {
                paginationContainer.appendChild(createPageButton(String(page), page, false, page === meta.current_page));
            });

        paginationContainer.appendChild(createPageButton('»', Math.min(meta.current_page + 1, meta.last_page), meta.current_page >= meta.last_page));
    };

    const buildStatusBadgeClass = (enrollment) => {
        if (enrollment.is_deleted) {
            return 'badge-error';
        }

        if (enrollment.status.key === 'completed') {
            return 'badge-success';
        }

        if (enrollment.status.key === 'in_progress') {
            return 'badge-info';
        }

        return 'badge-ghost';
    };

    const renderRows = (rows) => {
        tbody.innerHTML = '';

        rows.forEach((row) => {
            const fragment = template.content.cloneNode(true);
            const tr = fragment.querySelector('tr');
            const statusBadge = fragment.querySelector('[data-cell="status"]');
            const editButton = fragment.querySelector('[data-action="edit"]');
            const deleteButton = fragment.querySelector('[data-action="delete"]');
            const restoreButton = fragment.querySelector('[data-action="restore"]');

            fragment.querySelector('[data-cell="surname"]').textContent = row.user.surname || '-';
            fragment.querySelector('[data-cell="name"]').textContent = row.user.name || '-';
            fragment.querySelector('[data-cell="fiscal_code"]').textContent = row.user.fiscal_code || '-';
            fragment.querySelector('[data-cell="email"]').textContent = row.user.email || '-';
            fragment.querySelector('[data-cell="completion_percentage"]').textContent = `${row.completion_percentage ?? 0}%`;
            fragment.querySelector('[data-cell="assigned_at"]').textContent = row.assigned_at || '-';

            statusBadge.textContent = row.is_deleted ? 'Eliminato' : row.status.label;
            statusBadge.classList.add(buildStatusBadgeClass(row));

            if (editButton) {
                if (row.actions.edit_url) {
                    editButton.href = row.actions.edit_url;
                } else {
                    editButton.classList.add('btn-disabled');
                    editButton.removeAttribute('href');
                }
            }

            if (deleteButton) {
                deleteButton.classList.toggle('hidden', !row.actions.can_delete);
            }

            if (restoreButton) {
                restoreButton.classList.toggle('hidden', !row.actions.can_restore);
            }

            if (deleteButton && row.actions.can_delete) {
                deleteButton.addEventListener('click', async () => {
                    const shouldDelete = window.confirm('Sei sicuro di voler eliminare questa iscrizione?');

                    if (!shouldDelete) {
                        return;
                    }

                    try {
                        await window.axios.delete(row.actions.delete_url, {
                            headers: { Accept: 'application/json' },
                        });

                        await loadEnrollments();
                    } catch (error) {
                        window.alert('Errore durante l\'eliminazione dell\'iscrizione.');
                    }
                });
            }

            if (restoreButton && row.actions.can_restore) {
                restoreButton.addEventListener('click', async () => {
                    const shouldRestore = window.confirm('Vuoi ripristinare questa iscrizione?');

                    if (!shouldRestore) {
                        return;
                    }

                    try {
                        await window.axios.post(
                            row.actions.restore_url,
                            {},
                            { headers: { Accept: 'application/json' } },
                        );

                        await loadEnrollments();
                    } catch (error) {
                        const message = error.response?.data?.message || 'Errore durante il ripristino dell\'iscrizione.';
                        window.alert(message);
                    }
                });
            }

            if (row.is_deleted) {
                tr.classList.add('opacity-70');
            }

            tbody.appendChild(fragment);
        });
    };

    const renderUserSearchResults = (users) => {
        userResultsBody.innerHTML = '';

        users.forEach((user) => {
            const fragment = userRowTemplate.content.cloneNode(true);
            const selectButton = fragment.querySelector('[data-action="select-user"]');

            fragment.querySelector('[data-cell="id"]').textContent = user.id;
            fragment.querySelector('[data-cell="surname"]').textContent = user.surname || '-';
            fragment.querySelector('[data-cell="name"]').textContent = user.name || '-';
            fragment.querySelector('[data-cell="fiscal_code"]').textContent = user.fiscal_code || '-';
            fragment.querySelector('[data-cell="email"]').textContent = user.email || '-';

            selectButton.addEventListener('click', () => {
                state.selectedUserForEnrollment = user;
                state.confirmAction = 'create';
                state.restoreUrl = null;
                confirmEnrollmentMessage.textContent = `Confermi l'iscrizione di ${user.surname || ''} ${user.name || ''} (ID ${user.id}) a questo corso?`.trim();
                confirmEnrollmentModal.showModal();
            });

            userResultsBody.appendChild(fragment);
        });

        userResultsEmptyState.classList.toggle('hidden', users.length > 0);
    };

    const searchUsersForEnrollment = async () => {
        const search = userSearchInput.value.trim();

        if (search === '') {
            userResultsBody.innerHTML = '';
            userResultsEmptyState.classList.remove('hidden');

            return;
        }

        userSearchButton.disabled = true;

        try {
            const response = await window.axios.get(searchUsersApiUrl, {
                params: { search },
                headers: { Accept: 'application/json' },
            });

            const users = response.data.data ?? [];
            renderUserSearchResults(users);
        } catch (error) {
            userResultsBody.innerHTML = '';
            userResultsEmptyState.classList.remove('hidden');
            window.alert('Errore durante la ricerca utenti.');
        } finally {
            userSearchButton.disabled = false;
        }
    };

    const renderSummary = (meta) => {
        if (meta.total === 0 || meta.from === null || meta.to === null) {
            summaryElement.textContent = '0 iscritti';

            return;
        }

        summaryElement.textContent = `Mostrati ${meta.from}-${meta.to} di ${meta.total} iscritti`;
    };

    const loadEnrollments = async () => {
        if (state.loading) {
            return;
        }

        setLoadingState(true);

        try {
            const response = await window.axios.get(`${apiUrl}?${buildQueryString()}`, {
                headers: { Accept: 'application/json' },
            });

            const rows = response.data.data ?? [];
            const meta = response.data.meta ?? {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: 0,
                from: null,
                to: null,
            };

            if (rows.length === 0 && meta.total > 0 && state.page > 1) {
                state.page = Math.max(meta.last_page, 1);
                setLoadingState(false);
                await loadEnrollments();

                return;
            }

            renderRows(rows);
            renderSummary(meta);
            renderPagination(meta);
            emptyState.classList.toggle('hidden', rows.length > 0);
            updateSortIndicators();
        } catch (error) {
            tbody.innerHTML = '';
            emptyState.classList.remove('hidden');
            summaryElement.textContent = 'Errore nel caricamento degli iscritti.';
            paginationContainer.innerHTML = '';
        } finally {
            setLoadingState(false);
        }
    };

    openCreateEnrollmentModalButton.addEventListener('click', () => {
        userSearchInput.value = '';
        userResultsBody.innerHTML = '';
        userResultsEmptyState.classList.add('hidden');
        state.selectedUserForEnrollment = null;
        createEnrollmentModal.showModal();
    });

    closeCreateEnrollmentModalButton.addEventListener('click', () => {
        createEnrollmentModal.close();
    });

    userSearchButton.addEventListener('click', () => {
        searchUsersForEnrollment();
    });

    userSearchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        searchUsersForEnrollment();
    });

    confirmEnrollmentSubmitButton.addEventListener('click', async () => {
        if (!state.selectedUserForEnrollment) {
            return;
        }

        setButtonLoading(confirmEnrollmentSubmitButton, true, { loadingText: 'Salvataggio...' });

        try {
            if (state.confirmAction === 'restore') {
                if (!state.restoreUrl) {
                    setButtonLoading(confirmEnrollmentSubmitButton, false);

                    return;
                }

                await window.axios.post(
                    state.restoreUrl,
                    {},
                    { headers: { Accept: 'application/json' } },
                );
            } else {
                await window.axios.post(
                    storeApiUrl,
                    { user_id: state.selectedUserForEnrollment.id },
                    { headers: { Accept: 'application/json' } },
                );
            }

            confirmEnrollmentModal.close();
            createEnrollmentModal.close();
            state.confirmAction = 'create';
            state.restoreUrl = null;
            await loadEnrollments();
        } catch (error) {
            const responseData = error.response?.data;

            if (error.response?.status === 409 && responseData?.requires_restore && responseData?.restore_url) {
                state.confirmAction = 'restore';
                state.restoreUrl = responseData.restore_url;
                confirmEnrollmentMessage.textContent = responseData.message || 'Esiste già una iscrizione eliminata. Vuoi ripristinarla?';
            } else {
                const message = responseData?.message || 'Errore durante la creazione dell\'iscrizione.';
                window.alert(message);
            }
        } finally {
            setButtonLoading(confirmEnrollmentSubmitButton, false);
        }
    });

    searchButton.addEventListener('click', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        loadEnrollments();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        state.search = searchInput.value.trim();
        state.page = 1;
        loadEnrollments();
    });

    showTrashedCheckbox.addEventListener('change', () => {
        state.showTrashed = showTrashedCheckbox.checked;
        state.page = 1;
        loadEnrollments();
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const selectedSort = button.dataset.sortKey;

            if (state.sort !== selectedSort) {
                state.sort = selectedSort;
                state.direction = 'asc';
            } else {
                state.direction = cycleSortDirection(state.direction);

                if (state.direction === null) {
                    state.sort = 'surname';
                    state.direction = 'asc';
                }
            }

            state.page = 1;
            loadEnrollments();
        });
    });

    updateSortIndicators();
    loadEnrollments();
}

function initializeTeacherAssignmentsTable(courseEditPage) {
    const container = courseEditPage.querySelector('[data-teacher-assignments-table]');

    if (!container) {
        return;
    }

    const apiUrl = container.dataset.teacherAssignmentsApiUrl;
    const searchUsersApiUrl = container.dataset.teacherAssignmentsSearchUsersApiUrl;
    const storeApiUrl = container.dataset.teacherAssignmentsStoreApiUrl;
    const tbody = container.querySelector('[data-teacher-assignments-tbody]');
    const template = container.querySelector('[data-teacher-assignment-row-template]');
    const emptyState = container.querySelector('[data-teacher-assignments-empty]');
    const tableContainer = container.querySelector('[data-teacher-assignments-table-container]');
    const tableLoader = container.querySelector('[data-teacher-assignments-loader]');
    const searchInput = container.querySelector('[data-teacher-assignments-search]');
    const searchButton = container.querySelector('[data-teacher-assignments-search-button]');
    const showTrashedCheckbox = container.querySelector('[data-teacher-assignments-show-trashed]');
    const paginationContainer = container.querySelector('[data-teacher-assignments-pagination]');
    const summaryElement = container.querySelector('[data-teacher-assignments-summary]');
    const sortButtons = Array.from(container.querySelectorAll('[data-sort-key]'));
    const sortIndicators = Array.from(new Set(Array.from(container.querySelectorAll('[data-sort-indicator]')).map((indicator) => indicator.dataset.sortIndicator)));
    const openCreateModalButton = container.querySelector('[data-open-course-teacher-modal]');
    const createModal = container.querySelector('[data-create-course-teacher-modal]');
    const closeCreateModalButton = container.querySelector('[data-close-course-teacher-modal]');
    const userSearchInput = container.querySelector('[data-course-teacher-user-search]');
    const userSearchButton = container.querySelector('[data-course-teacher-user-search-button]');
    const userResultsBody = container.querySelector('[data-course-teacher-user-results]');
    const userResultsEmptyState = container.querySelector('[data-course-teacher-user-results-empty]');
    const userRowTemplate = container.querySelector('[data-course-teacher-user-row-template]');
    const confirmModal = container.querySelector('[data-confirm-course-teacher-modal]');
    const confirmMessage = container.querySelector('[data-confirm-course-teacher-message]');
    const confirmSubmitButton = container.querySelector('[data-confirm-course-teacher-submit]');

    if (!apiUrl || !searchUsersApiUrl || !storeApiUrl || !tbody || !template || !emptyState || !tableContainer || !tableLoader || !searchInput || !searchButton || !showTrashedCheckbox || !paginationContainer || !summaryElement || !openCreateModalButton || !createModal || !closeCreateModalButton || !userSearchInput || !userSearchButton || !userResultsBody || !userResultsEmptyState || !userRowTemplate || !confirmModal || !confirmMessage || !confirmSubmitButton) {
        return;
    }

    const state = {
        page: 1,
        search: '',
        showTrashed: false,
        sort: 'surname',
        direction: 'asc',
        loading: false,
        selectedUser: null,
        confirmAction: 'create',
        restoreUrl: null,
    };

    const updateSortIndicators = () => {
        sortIndicators.forEach((key) => {
            const icons = container.querySelectorAll(`[data-sort-indicator="${key}"]`);

            icons.forEach((icon) => {
                const iconType = icon.dataset.sortIcon;
                let shouldShow = false;

                if (state.sort !== key) {
                    shouldShow = iconType === 'none';
                } else if (state.direction === 'asc') {
                    shouldShow = iconType === 'asc';
                } else if (state.direction === 'desc') {
                    shouldShow = iconType === 'desc';
                }

                icon.classList.toggle('hidden', !shouldShow);
            });
        });
    };

    const buildQueryString = () => {
        const params = new URLSearchParams();

        params.set('page', String(state.page));
        params.set('show_trashed', state.showTrashed ? '1' : '0');

        if (state.search !== '') {
            params.set('search', state.search);
        }

        if (state.sort !== null && state.direction !== null) {
            params.set('sort', state.sort);
            params.set('direction', state.direction);
        }

        return params.toString();
    };

    const setLoadingState = (loading) => {
        state.loading = loading;
        toggleAsyncTableLoading({ scope: container, container: tableContainer, loader: tableLoader }, loading);
    };

    const cycleSortDirection = (currentDirection) => {
        if (currentDirection === null) {
            return 'asc';
        }

        if (currentDirection === 'asc') {
            return 'desc';
        }

        return null;
    };

    const renderPagination = (meta) => {
        paginationContainer.innerHTML = '';

        if (meta.last_page <= 1) {
            return;
        }

        const createPageButton = (label, page, disabled = false, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `join-item btn btn-sm ${active ? 'btn-primary' : 'btn-ghost'}`;
            button.textContent = label;
            button.disabled = disabled;

            if (!disabled) {
                button.addEventListener('click', () => {
                    state.page = page;
                    loadTeacherAssignments();
                });
            }

            return button;
        };

        paginationContainer.appendChild(createPageButton('«', Math.max(meta.current_page - 1, 1), meta.current_page <= 1));

        const pagesToRender = new Set([
            1,
            meta.last_page,
            Math.max(meta.current_page - 1, 1),
            meta.current_page,
            Math.min(meta.current_page + 1, meta.last_page),
        ]);

        Array.from(pagesToRender)
            .sort((a, b) => a - b)
            .forEach((page) => {
                paginationContainer.appendChild(createPageButton(String(page), page, false, page === meta.current_page));
            });

        paginationContainer.appendChild(createPageButton('»', Math.min(meta.current_page + 1, meta.last_page), meta.current_page >= meta.last_page));
    };

    const renderRows = (rows) => {
        tbody.innerHTML = '';

        rows.forEach((row) => {
            const fragment = template.content.cloneNode(true);
            const tr = fragment.querySelector('tr');
            const deleteButton = fragment.querySelector('[data-action="delete"]');
            const restoreButton = fragment.querySelector('[data-action="restore"]');

            fragment.querySelector('[data-cell="surname"]').textContent = row.user.surname || '-';
            fragment.querySelector('[data-cell="name"]').textContent = row.user.name || '-';
            fragment.querySelector('[data-cell="fiscal_code"]').textContent = row.user.fiscal_code || '-';
            fragment.querySelector('[data-cell="email"]').textContent = row.user.email || '-';
            fragment.querySelector('[data-cell="assigned_at"]').textContent = row.assigned_at || '-';

            deleteButton.classList.toggle('hidden', !row.actions.can_delete);
            restoreButton.classList.toggle('hidden', !row.actions.can_restore);

            if (row.actions.can_delete) {
                deleteButton.addEventListener('click', async () => {
                    if (!window.confirm('Sei sicuro di voler rimuovere questo docente dal corso?')) {
                        return;
                    }

                    try {
                        await window.axios.delete(row.actions.delete_url, {
                            headers: { Accept: 'application/json' },
                        });

                        await loadTeacherAssignments();
                    } catch (error) {
                        window.alert(error.response?.data?.message || 'Errore durante la rimozione del docente.');
                    }
                });
            }

            if (row.actions.can_restore) {
                restoreButton.addEventListener('click', async () => {
                    if (!window.confirm('Vuoi ripristinare questo docente del corso?')) {
                        return;
                    }

                    try {
                        await window.axios.post(
                            row.actions.restore_url,
                            {},
                            { headers: { Accept: 'application/json' } },
                        );

                        await loadTeacherAssignments();
                    } catch (error) {
                        window.alert(error.response?.data?.message || 'Errore durante il ripristino del docente.');
                    }
                });
            }

            if (row.is_deleted) {
                tr.classList.add('opacity-70');
            }

            tbody.appendChild(fragment);
        });
    };

    const renderUserSearchResults = (users) => {
        userResultsBody.innerHTML = '';

        users.forEach((user) => {
            const fragment = userRowTemplate.content.cloneNode(true);
            const selectButton = fragment.querySelector('[data-action="select-user"]');

            fragment.querySelector('[data-cell="id"]').textContent = user.id;
            fragment.querySelector('[data-cell="surname"]').textContent = user.surname || '-';
            fragment.querySelector('[data-cell="name"]').textContent = user.name || '-';
            fragment.querySelector('[data-cell="fiscal_code"]').textContent = user.fiscal_code || '-';
            fragment.querySelector('[data-cell="email"]').textContent = user.email || '-';

            selectButton.addEventListener('click', () => {
                state.selectedUser = user;
                state.confirmAction = 'create';
                state.restoreUrl = null;
                confirmMessage.textContent = `Confermi l'assegnazione di ${user.surname || ''} ${user.name || ''} (ID ${user.id}) come docente del corso?`.trim();
                confirmModal.showModal();
            });

            userResultsBody.appendChild(fragment);
        });

        userResultsEmptyState.classList.toggle('hidden', users.length > 0);
    };

    const searchUsers = async () => {
        const search = userSearchInput.value.trim();

        if (search === '') {
            userResultsBody.innerHTML = '';
            userResultsEmptyState.classList.remove('hidden');

            return;
        }

        userSearchButton.disabled = true;

        try {
            const response = await window.axios.get(searchUsersApiUrl, {
                params: { search },
                headers: { Accept: 'application/json' },
            });

            renderUserSearchResults(response.data.data ?? []);
        } catch (error) {
            userResultsBody.innerHTML = '';
            userResultsEmptyState.classList.remove('hidden');
            window.alert('Errore durante la ricerca docenti.');
        } finally {
            userSearchButton.disabled = false;
        }
    };

    const renderSummary = (meta) => {
        if (meta.total === 0 || meta.from === null || meta.to === null) {
            summaryElement.textContent = '0 docenti';

            return;
        }

        summaryElement.textContent = `Mostrati ${meta.from}-${meta.to} di ${meta.total} docenti`;
    };

    const loadTeacherAssignments = async () => {
        if (state.loading) {
            return;
        }

        setLoadingState(true);

        try {
            const response = await window.axios.get(`${apiUrl}?${buildQueryString()}`, {
                headers: { Accept: 'application/json' },
            });

            const rows = response.data.data ?? [];
            const meta = response.data.meta ?? {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: 0,
                from: null,
                to: null,
            };

            if (rows.length === 0 && meta.total > 0 && state.page > 1) {
                state.page = Math.max(meta.last_page, 1);
                setLoadingState(false);
                await loadTeacherAssignments();

                return;
            }

            renderRows(rows);
            renderSummary(meta);
            renderPagination(meta);
            emptyState.classList.toggle('hidden', rows.length > 0);
            updateSortIndicators();
        } catch (error) {
            tbody.innerHTML = '';
            emptyState.classList.remove('hidden');
            summaryElement.textContent = 'Errore nel caricamento dei docenti.';
            paginationContainer.innerHTML = '';
        } finally {
            setLoadingState(false);
        }
    };

    openCreateModalButton.addEventListener('click', () => {
        userSearchInput.value = '';
        userResultsBody.innerHTML = '';
        userResultsEmptyState.classList.add('hidden');
        state.selectedUser = null;
        createModal.showModal();
    });

    closeCreateModalButton.addEventListener('click', () => {
        createModal.close();
    });

    userSearchButton.addEventListener('click', () => {
        searchUsers();
    });

    userSearchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        searchUsers();
    });

    confirmSubmitButton.addEventListener('click', async () => {
        if (!state.selectedUser) {
            return;
        }

        setButtonLoading(confirmSubmitButton, true, { loadingText: 'Salvataggio...' });

        try {
            if (state.confirmAction === 'restore') {
                if (!state.restoreUrl) {
                    setButtonLoading(confirmSubmitButton, false);

                    return;
                }

                await window.axios.post(
                    state.restoreUrl,
                    {},
                    { headers: { Accept: 'application/json' } },
                );
            } else {
                await window.axios.post(
                    storeApiUrl,
                    { user_id: state.selectedUser.id },
                    { headers: { Accept: 'application/json' } },
                );
            }

            confirmModal.close();
            createModal.close();
            state.confirmAction = 'create';
            state.restoreUrl = null;
            await loadTeacherAssignments();
        } catch (error) {
            const responseData = error.response?.data;

            if (error.response?.status === 409 && responseData?.requires_restore && responseData?.restore_url) {
                state.confirmAction = 'restore';
                state.restoreUrl = responseData.restore_url;
                confirmMessage.textContent = responseData.message || 'Esiste già una assegnazione docente eliminata. Vuoi ripristinarla?';
            } else {
                window.alert(responseData?.message || 'Errore durante l\'assegnazione del docente.');
            }
        } finally {
            setButtonLoading(confirmSubmitButton, false);
        }
    });

    searchButton.addEventListener('click', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        loadTeacherAssignments();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        state.search = searchInput.value.trim();
        state.page = 1;
        loadTeacherAssignments();
    });

    showTrashedCheckbox.addEventListener('change', () => {
        state.showTrashed = showTrashedCheckbox.checked;
        state.page = 1;
        loadTeacherAssignments();
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const selectedSort = button.dataset.sortKey;

            if (state.sort !== selectedSort) {
                state.sort = selectedSort;
                state.direction = 'asc';
            } else {
                state.direction = cycleSortDirection(state.direction);

                if (state.direction === null) {
                    state.sort = 'surname';
                    state.direction = 'asc';
                }
            }

            state.page = 1;
            loadTeacherAssignments();
        });
    });

    updateSortIndicators();
    loadTeacherAssignments();
}

function initializeTutorAssignmentsTable(courseEditPage) {
    const container = courseEditPage.querySelector('[data-tutor-assignments-table]');

    if (!container) {
        return;
    }

    const apiUrl = container.dataset.tutorAssignmentsApiUrl;
    const searchUsersApiUrl = container.dataset.tutorAssignmentsSearchUsersApiUrl;
    const storeApiUrl = container.dataset.tutorAssignmentsStoreApiUrl;
    const tbody = container.querySelector('[data-tutor-assignments-tbody]');
    const template = container.querySelector('[data-tutor-assignment-row-template]');
    const emptyState = container.querySelector('[data-tutor-assignments-empty]');
    const tableContainer = container.querySelector('[data-tutor-assignments-table-container]');
    const tableLoader = container.querySelector('[data-tutor-assignments-loader]');
    const searchInput = container.querySelector('[data-tutor-assignments-search]');
    const searchButton = container.querySelector('[data-tutor-assignments-search-button]');
    const showTrashedCheckbox = container.querySelector('[data-tutor-assignments-show-trashed]');
    const paginationContainer = container.querySelector('[data-tutor-assignments-pagination]');
    const summaryElement = container.querySelector('[data-tutor-assignments-summary]');
    const sortButtons = Array.from(container.querySelectorAll('[data-tutor-sort-key]'));
    const sortIndicators = Array.from(new Set(Array.from(container.querySelectorAll('[data-tutor-sort-indicator]')).map((indicator) => indicator.dataset.tutorSortIndicator)));
    const openCreateModalButton = container.querySelector('[data-open-course-tutor-modal]');
    const createModal = container.querySelector('[data-create-course-tutor-modal]');
    const closeCreateModalButton = container.querySelector('[data-close-course-tutor-modal]');
    const userSearchInput = container.querySelector('[data-course-tutor-user-search]');
    const userSearchButton = container.querySelector('[data-course-tutor-user-search-button]');
    const userResultsBody = container.querySelector('[data-course-tutor-user-results]');
    const userResultsEmptyState = container.querySelector('[data-course-tutor-user-results-empty]');
    const userRowTemplate = container.querySelector('[data-course-tutor-user-row-template]');
    const confirmModal = container.querySelector('[data-confirm-course-tutor-modal]');
    const confirmMessage = container.querySelector('[data-confirm-course-tutor-message]');
    const confirmSubmitButton = container.querySelector('[data-confirm-course-tutor-submit]');

    if (!apiUrl || !searchUsersApiUrl || !storeApiUrl || !tbody || !template || !emptyState || !tableContainer || !tableLoader || !searchInput || !searchButton || !showTrashedCheckbox || !paginationContainer || !summaryElement || !openCreateModalButton || !createModal || !closeCreateModalButton || !userSearchInput || !userSearchButton || !userResultsBody || !userResultsEmptyState || !userRowTemplate || !confirmModal || !confirmMessage || !confirmSubmitButton) {
        return;
    }

    const state = {
        page: 1,
        search: '',
        showTrashed: false,
        sort: 'surname',
        direction: 'asc',
        loading: false,
        selectedUser: null,
        confirmAction: 'create',
        restoreUrl: null,
    };

    const updateSortIndicators = () => {
        sortIndicators.forEach((key) => {
            const icons = container.querySelectorAll(`[data-tutor-sort-indicator="${key}"]`);

            icons.forEach((icon) => {
                const iconType = icon.dataset.tutorSortIcon;
                let shouldShow = false;

                if (state.sort !== key) {
                    shouldShow = iconType === 'none';
                } else if (state.direction === 'asc') {
                    shouldShow = iconType === 'asc';
                } else if (state.direction === 'desc') {
                    shouldShow = iconType === 'desc';
                }

                icon.classList.toggle('hidden', !shouldShow);
            });
        });
    };

    const buildQueryString = () => {
        const params = new URLSearchParams();

        params.set('page', String(state.page));
        params.set('show_trashed', state.showTrashed ? '1' : '0');

        if (state.search !== '') {
            params.set('search', state.search);
        }

        if (state.sort !== null && state.direction !== null) {
            params.set('sort', state.sort);
            params.set('direction', state.direction);
        }

        return params.toString();
    };

    const setLoadingState = (loading) => {
        state.loading = loading;
        toggleAsyncTableLoading({ scope: container, container: tableContainer, loader: tableLoader }, loading);
    };

    const cycleSortDirection = (currentDirection) => {
        if (currentDirection === null) {
            return 'asc';
        }

        if (currentDirection === 'asc') {
            return 'desc';
        }

        return null;
    };

    const renderPagination = (meta) => {
        paginationContainer.innerHTML = '';

        if (meta.last_page <= 1) {
            return;
        }

        const createPageButton = (label, page, disabled = false, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `join-item btn btn-sm ${active ? 'btn-primary' : 'btn-ghost'}`;
            button.textContent = label;
            button.disabled = disabled;

            if (!disabled) {
                button.addEventListener('click', () => {
                    state.page = page;
                    loadTutorAssignments();
                });
            }

            return button;
        };

        paginationContainer.appendChild(createPageButton('«', Math.max(meta.current_page - 1, 1), meta.current_page <= 1));

        const pagesToRender = new Set([
            1,
            meta.last_page,
            Math.max(meta.current_page - 1, 1),
            meta.current_page,
            Math.min(meta.current_page + 1, meta.last_page),
        ]);

        Array.from(pagesToRender)
            .sort((a, b) => a - b)
            .forEach((page) => {
                paginationContainer.appendChild(createPageButton(String(page), page, false, page === meta.current_page));
            });

        paginationContainer.appendChild(createPageButton('»', Math.min(meta.current_page + 1, meta.last_page), meta.current_page >= meta.last_page));
    };

    const renderRows = (rows) => {
        tbody.innerHTML = '';

        rows.forEach((row) => {
            const fragment = template.content.cloneNode(true);
            const tr = fragment.querySelector('tr');
            const deleteButton = fragment.querySelector('[data-action="delete"]');
            const restoreButton = fragment.querySelector('[data-action="restore"]');

            fragment.querySelector('[data-cell="surname"]').textContent = row.user.surname || '-';
            fragment.querySelector('[data-cell="name"]').textContent = row.user.name || '-';
            fragment.querySelector('[data-cell="fiscal_code"]').textContent = row.user.fiscal_code || '-';
            fragment.querySelector('[data-cell="email"]').textContent = row.user.email || '-';
            fragment.querySelector('[data-cell="assigned_at"]').textContent = row.assigned_at || '-';

            deleteButton.classList.toggle('hidden', !row.actions.can_delete);
            restoreButton.classList.toggle('hidden', !row.actions.can_restore);

            if (row.actions.can_delete) {
                deleteButton.addEventListener('click', async () => {
                    if (!window.confirm('Sei sicuro di voler rimuovere questo tutor dal corso?')) {
                        return;
                    }

                    try {
                        await window.axios.delete(row.actions.delete_url, {
                            headers: { Accept: 'application/json' },
                        });

                        await loadTutorAssignments();
                    } catch (error) {
                        window.alert(error.response?.data?.message || 'Errore durante la rimozione del tutor.');
                    }
                });
            }

            if (row.actions.can_restore) {
                restoreButton.addEventListener('click', async () => {
                    if (!window.confirm('Vuoi ripristinare questo tutor del corso?')) {
                        return;
                    }

                    try {
                        await window.axios.post(
                            row.actions.restore_url,
                            {},
                            { headers: { Accept: 'application/json' } },
                        );

                        await loadTutorAssignments();
                    } catch (error) {
                        window.alert(error.response?.data?.message || 'Errore durante il ripristino del tutor.');
                    }
                });
            }

            if (row.is_deleted) {
                tr.classList.add('opacity-70');
            }

            tbody.appendChild(fragment);
        });
    };

    const renderUserSearchResults = (users) => {
        userResultsBody.innerHTML = '';

        users.forEach((user) => {
            const fragment = userRowTemplate.content.cloneNode(true);
            const selectButton = fragment.querySelector('[data-action="select-user"]');

            fragment.querySelector('[data-cell="id"]').textContent = user.id;
            fragment.querySelector('[data-cell="surname"]').textContent = user.surname || '-';
            fragment.querySelector('[data-cell="name"]').textContent = user.name || '-';
            fragment.querySelector('[data-cell="fiscal_code"]').textContent = user.fiscal_code || '-';
            fragment.querySelector('[data-cell="email"]').textContent = user.email || '-';

            selectButton.addEventListener('click', () => {
                state.selectedUser = user;
                state.confirmAction = 'create';
                state.restoreUrl = null;
                confirmMessage.textContent = `Confermi l'assegnazione di ${user.surname || ''} ${user.name || ''} (ID ${user.id}) come tutor del corso?`.trim();
                confirmModal.showModal();
            });

            userResultsBody.appendChild(fragment);
        });

        userResultsEmptyState.classList.toggle('hidden', users.length > 0);
    };

    const searchUsers = async () => {
        const search = userSearchInput.value.trim();

        if (search === '') {
            userResultsBody.innerHTML = '';
            userResultsEmptyState.classList.remove('hidden');

            return;
        }

        userSearchButton.disabled = true;

        try {
            const response = await window.axios.get(searchUsersApiUrl, {
                params: { search },
                headers: { Accept: 'application/json' },
            });

            renderUserSearchResults(response.data.data ?? []);
        } catch (error) {
            userResultsBody.innerHTML = '';
            userResultsEmptyState.classList.remove('hidden');
            window.alert('Errore durante la ricerca tutor.');
        } finally {
            userSearchButton.disabled = false;
        }
    };

    const renderSummary = (meta) => {
        if (meta.total === 0 || meta.from === null || meta.to === null) {
            summaryElement.textContent = '0 tutor';

            return;
        }

        summaryElement.textContent = `Mostrati ${meta.from}-${meta.to} di ${meta.total} tutor`;
    };

    const loadTutorAssignments = async () => {
        if (state.loading) {
            return;
        }

        setLoadingState(true);

        try {
            const response = await window.axios.get(`${apiUrl}?${buildQueryString()}`, {
                headers: { Accept: 'application/json' },
            });

            const rows = response.data.data ?? [];
            const meta = response.data.meta ?? {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: 0,
                from: null,
                to: null,
            };

            if (rows.length === 0 && meta.total > 0 && state.page > 1) {
                state.page = Math.max(meta.last_page, 1);
                setLoadingState(false);
                await loadTutorAssignments();

                return;
            }

            renderRows(rows);
            renderSummary(meta);
            renderPagination(meta);
            emptyState.classList.toggle('hidden', rows.length > 0);
            updateSortIndicators();
        } catch (error) {
            tbody.innerHTML = '';
            emptyState.classList.remove('hidden');
            summaryElement.textContent = 'Errore nel caricamento dei tutor.';
            paginationContainer.innerHTML = '';
        } finally {
            setLoadingState(false);
        }
    };

    openCreateModalButton.addEventListener('click', () => {
        userSearchInput.value = '';
        userResultsBody.innerHTML = '';
        userResultsEmptyState.classList.add('hidden');
        state.selectedUser = null;
        createModal.showModal();
    });

    closeCreateModalButton.addEventListener('click', () => {
        createModal.close();
    });

    userSearchButton.addEventListener('click', () => {
        searchUsers();
    });

    userSearchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        searchUsers();
    });

    confirmSubmitButton.addEventListener('click', async () => {
        if (!state.selectedUser) {
            return;
        }

        setButtonLoading(confirmSubmitButton, true, { loadingText: 'Salvataggio...' });

        try {
            if (state.confirmAction === 'restore') {
                if (!state.restoreUrl) {
                    setButtonLoading(confirmSubmitButton, false);

                    return;
                }

                await window.axios.post(
                    state.restoreUrl,
                    {},
                    { headers: { Accept: 'application/json' } },
                );
            } else {
                await window.axios.post(
                    storeApiUrl,
                    { user_id: state.selectedUser.id },
                    { headers: { Accept: 'application/json' } },
                );
            }

            confirmModal.close();
            createModal.close();
            state.confirmAction = 'create';
            state.restoreUrl = null;
            await loadTutorAssignments();
        } catch (error) {
            const responseData = error.response?.data;

            if (error.response?.status === 409 && responseData?.requires_restore && responseData?.restore_url) {
                state.confirmAction = 'restore';
                state.restoreUrl = responseData.restore_url;
                confirmMessage.textContent = responseData.message || 'Esiste già una assegnazione tutor eliminata. Vuoi ripristinarla?';
            } else {
                window.alert(responseData?.message || 'Errore durante l\'assegnazione del tutor.');
            }
        } finally {
            setButtonLoading(confirmSubmitButton, false);
        }
    });

    searchButton.addEventListener('click', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        loadTutorAssignments();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        state.search = searchInput.value.trim();
        state.page = 1;
        loadTutorAssignments();
    });

    showTrashedCheckbox.addEventListener('change', () => {
        state.showTrashed = showTrashedCheckbox.checked;
        state.page = 1;
        loadTutorAssignments();
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const selectedSort = button.dataset.tutorSortKey;

            if (state.sort !== selectedSort) {
                state.sort = selectedSort;
                state.direction = 'asc';
            } else {
                state.direction = cycleSortDirection(state.direction);

                if (state.direction === null) {
                    state.sort = 'surname';
                    state.direction = 'asc';
                }
            }

            state.page = 1;
            loadTutorAssignments();
        });
    });

    updateSortIndicators();
    loadTutorAssignments();
}
