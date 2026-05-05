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
    initializeEnrollmentsTable(courseEditPage);
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

    if (!apiUrl || !searchUsersApiUrl || !storeApiUrl || !tbody || !template || !emptyState || !searchInput || !searchButton || !showTrashedCheckbox || !paginationContainer || !summaryElement || !openCreateEnrollmentModalButton || !createEnrollmentModal || !closeCreateEnrollmentModalButton || !userSearchInput || !userSearchButton || !userResultsBody || !userResultsEmptyState || !userRowTemplate || !confirmEnrollmentModal || !confirmEnrollmentMessage || !confirmEnrollmentSubmitButton) {
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
        container.classList.toggle('pointer-events-none', loading);
        container.classList.toggle('opacity-70', loading);
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

            if (!row.actions.can_delete) {
                deleteButton.disabled = true;
                deleteButton.classList.add('btn-disabled');
            } else {
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

        confirmEnrollmentSubmitButton.disabled = true;

        try {
            await window.axios.post(
                storeApiUrl,
                { user_id: state.selectedUserForEnrollment.id },
                { headers: { Accept: 'application/json' } },
            );

            confirmEnrollmentModal.close();
            createEnrollmentModal.close();
            await loadEnrollments();
        } catch (error) {
            const message = error.response?.data?.message || 'Errore durante la creazione dell\'iscrizione.';
            window.alert(message);
        } finally {
            confirmEnrollmentSubmitButton.disabled = false;
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
