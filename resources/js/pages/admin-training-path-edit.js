import { setButtonLoading, toggleAsyncTableLoading } from '../ui/loading-state';

document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-training-path-edit-page]');

    if (!page) {
        return;
    }

    initializeCourseSelection(page);
    initializeRecipients(page);
    initializeEnrollments(page);
});

function initializeCourseSelection(scope) {
    const form = scope.querySelector('[data-training-path-courses-form]');

    if (!form) {
        return;
    }

    const apiUrl = scope.dataset.trainingPathCoursesApiUrl;
    const openModalButton = scope.querySelector('[data-open-training-path-courses-modal]');
    const modal = scope.querySelector('[data-training-path-courses-modal]');
    const closeModalButton = scope.querySelector('[data-close-training-path-courses-modal]');
    const confirmButton = scope.querySelector('[data-confirm-training-path-courses]');
    const selectedWrapper = scope.querySelector('[data-training-path-selected-courses-wrapper]');
    const selectedEmpty = scope.querySelector('[data-training-path-selected-courses-empty]');
    const selectedCount = scope.querySelector('[data-training-path-selected-courses-count]');
    const selectedSortable = scope.querySelector('[data-training-path-selected-courses-sortable]');
    const hiddenInputs = scope.querySelector('[data-training-path-course-hidden-inputs]');
    const modalSelectedCount = scope.querySelector('[data-training-path-modal-selected-courses-count]');
    const selectedChips = scope.querySelector('[data-training-path-selected-course-chips]');
    const modalSelectedSortable = scope.querySelector('[data-training-path-modal-selected-courses-sortable]');
    const modalSelectedEmpty = scope.querySelector('[data-training-path-modal-selected-courses-empty]');
    const searchInput = scope.querySelector('[data-training-path-courses-search]');
    const searchButton = scope.querySelector('[data-training-path-courses-search-button]');
    const tableContainer = scope.querySelector('[data-training-path-courses-table-container]');
    const tableLoader = scope.querySelector('[data-training-path-courses-loader]');
    const tbody = scope.querySelector('[data-training-path-courses-tbody]');
    const emptyState = scope.querySelector('[data-training-path-courses-empty]');
    const summaryElement = scope.querySelector('[data-training-path-courses-summary]');
    const paginationContainer = scope.querySelector('[data-training-path-courses-pagination]');
    const sortButtons = Array.from(scope.querySelectorAll('[data-training-path-courses-sort-key]'));
    const rowTemplate = scope.querySelector('[data-training-path-course-table-row-template]');
    const chipTemplate = scope.querySelector('[data-training-path-selected-course-chip-template]');
    const itemTemplate = scope.querySelector('[data-training-path-selected-course-item-template]');
    const selectedCoursesScript = scope.querySelector('[data-training-path-selected-courses]');

    if (!apiUrl || !openModalButton || !(modal instanceof HTMLDialogElement) || !closeModalButton || !confirmButton || !selectedWrapper || !selectedEmpty || !selectedCount || !selectedSortable || !hiddenInputs || !modalSelectedCount || !selectedChips || !modalSelectedSortable || !modalSelectedEmpty || !searchInput || !searchButton || !tableContainer || !tableLoader || !tbody || !emptyState || !summaryElement || !paginationContainer || !(rowTemplate instanceof HTMLTemplateElement) || !(chipTemplate instanceof HTMLTemplateElement) || !(itemTemplate instanceof HTMLTemplateElement) || !selectedCoursesScript) {
        return;
    }

    const cloneCourses = (courses) => courses.map((course) => ({
        ...course,
        type: { ...course.type },
        status: { ...course.status },
    }));

    const normalizeCourses = (courses) => courses.map((course, index) => ({
        ...course,
        sort_order: index + 1,
    }));

    const state = {
        page: 1,
        search: '',
        sort: 'id',
        direction: 'desc',
        loading: false,
        selectedCourses: normalizeCourses(cloneCourses(JSON.parse(selectedCoursesScript.textContent || '[]'))),
        draftCourses: [],
        draggedCourseId: null,
        submitting: false,
    };

    const setLoadingState = (loading) => {
        state.loading = loading;
        toggleAsyncTableLoading({ scope, container: tableContainer, loader: tableLoader }, loading);
    };

    const buildQueryString = () => {
        const params = new URLSearchParams();

        params.set('page', String(state.page));

        if (state.search !== '') {
            params.set('search', state.search);
        }

        params.set('sort', state.sort);
        params.set('direction', state.direction);

        return params.toString();
    };

    const cycleSortDirection = (currentDirection) => {
        if (currentDirection === 'asc') {
            return 'desc';
        }

        return 'asc';
    };

    const syncHiddenInputs = () => {
        hiddenInputs.innerHTML = '';

        state.selectedCourses.forEach((course) => {
            const courseInput = document.createElement('input');
            courseInput.type = 'hidden';
            courseInput.name = 'course_ids[]';
            courseInput.value = String(course.id);
            hiddenInputs.appendChild(courseInput);

            const orderInput = document.createElement('input');
            orderInput.type = 'hidden';
            orderInput.name = `course_orders[${course.id}]`;
            orderInput.value = String(course.sort_order);
            hiddenInputs.appendChild(orderInput);
        });
    };

    const renderSelectedCounts = () => {
        const selectedCountLabel = `${state.selectedCourses.length} ${state.selectedCourses.length === 1 ? 'corso' : 'corsi'}`;
        const draftCountLabel = `${state.draftCourses.length} ${state.draftCourses.length === 1 ? 'corso' : 'corsi'}`;

        selectedCount.textContent = selectedCountLabel;
        modalSelectedCount.textContent = draftCountLabel;
    };

    const renderSelectedCourseItem = (course, container, { interactive = false } = {}) => {
        const fragment = itemTemplate.content.cloneNode(true);
        const item = fragment.querySelector('[data-training-path-selected-course-item]');
        const removeButton = fragment.querySelector('[data-action="remove-selected-course"]');
        const dragHandle = fragment.querySelector('.cursor-move');

        if (!item || !removeButton) {
            return;
        }

        item.dataset.courseId = String(course.id);
        item.draggable = interactive;
        fragment.querySelector('[data-item-title]').textContent = course.title;
        fragment.querySelector('[data-item-meta]').textContent = `${course.code || '-'} · ${course.year ?? '-'}`;
        fragment.querySelector('[data-item-type]').textContent = course.type.label;
        fragment.querySelector('[data-item-status]').textContent = course.status.label;

        removeButton.classList.toggle('hidden', !interactive);
        dragHandle?.classList.toggle('cursor-move', interactive);
        dragHandle?.classList.toggle('cursor-default', !interactive);

        if (interactive) {
            removeButton.addEventListener('click', () => {
                state.draftCourses = normalizeCourses(
                    state.draftCourses.filter((selectedCourse) => Number(selectedCourse.id) !== Number(course.id)),
                );
                renderDraftCourses();
                void loadCourses();
            });

            item.addEventListener('dragstart', () => {
                state.draggedCourseId = Number(course.id);
                item.classList.add('opacity-50', 'shadow-lg', 'ring-2', 'ring-primary/30');
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('opacity-50', 'shadow-lg', 'ring-2', 'ring-primary/30');
                state.draftCourses = normalizeCourses(
                    Array.from(item.parentElement.querySelectorAll('[data-training-path-selected-course-item]'))
                        .map((selectedItem) => state.draftCourses.find((selectedCourse) => Number(selectedCourse.id) === Number(selectedItem.dataset.courseId)))
                        .filter(Boolean),
                );
                state.draggedCourseId = null;
                renderDraftCourses();
            });
        }

        container.appendChild(fragment);
    };

    const renderCommittedCourses = () => {
        selectedSortable.innerHTML = '';

        const hasSelectedCourses = state.selectedCourses.length > 0;

        selectedWrapper.classList.toggle('hidden', !hasSelectedCourses);
        selectedEmpty.classList.toggle('hidden', hasSelectedCourses);

        state.selectedCourses.forEach((course) => {
            renderSelectedCourseItem(course, selectedSortable);
        });

        syncHiddenInputs();
        renderSelectedCounts();
    };

    const renderDraftCourses = () => {
        modalSelectedSortable.innerHTML = '';
        selectedChips.innerHTML = '';

        const hasSelectedCourses = state.draftCourses.length > 0;

        modalSelectedSortable.classList.toggle('hidden', !hasSelectedCourses);
        modalSelectedEmpty.classList.toggle('hidden', hasSelectedCourses);

        state.draftCourses.forEach((course) => {
            renderSelectedCourseItem(course, modalSelectedSortable, { interactive: true });

            const chip = chipTemplate.content.cloneNode(true);
            const button = chip.querySelector('[data-action="remove-chip"]');

            chip.querySelector('[data-chip-label]').textContent = course.title;
            button?.addEventListener('click', () => {
                state.draftCourses = normalizeCourses(
                    state.draftCourses.filter((selectedCourse) => Number(selectedCourse.id) !== Number(course.id)),
                );
                renderDraftCourses();
                void loadCourses();
            });
            selectedChips.appendChild(chip);
        });

        renderSelectedCounts();
    };

    const moveDraggedItem = (container, targetItem, event) => {
        if (state.draggedCourseId === null || !targetItem || Number(targetItem.dataset.courseId) === state.draggedCourseId) {
            return;
        }

        const draggedItem = container.querySelector(`[data-course-id="${state.draggedCourseId}"]`);

        if (!draggedItem) {
            return;
        }

        const targetBounds = targetItem.getBoundingClientRect();
        const shouldInsertAfter = event.clientY > targetBounds.top + targetBounds.height / 2;

        if (shouldInsertAfter) {
            container.insertBefore(draggedItem, targetItem.nextSibling);
        } else {
            container.insertBefore(draggedItem, targetItem);
        }
    };

    modalSelectedSortable.addEventListener('dragover', (event) => {
        if (state.draggedCourseId === null) {
            return;
        }

        event.preventDefault();
        const targetItem = event.target.closest('[data-training-path-selected-course-item]');
        moveDraggedItem(modalSelectedSortable, targetItem, event);
    });

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
                    void loadCourses();
                });
            }

            return button;
        };

        paginationContainer.appendChild(createPageButton('<', Math.max(meta.current_page - 1, 1), meta.current_page <= 1));

        const pagesToRender = new Set([
            1,
            meta.last_page,
            Math.max(meta.current_page - 1, 1),
            meta.current_page,
            Math.min(meta.current_page + 1, meta.last_page),
        ]);

        Array.from(pagesToRender)
            .sort((first, second) => first - second)
            .forEach((page) => {
                paginationContainer.appendChild(createPageButton(String(page), page, false, page === meta.current_page));
            });

        paginationContainer.appendChild(createPageButton('>', Math.min(meta.current_page + 1, meta.last_page), meta.current_page >= meta.last_page));
    };

    const renderSummary = (meta) => {
        if (meta.total === 0 || meta.from === null || meta.to === null) {
            summaryElement.textContent = '0 corsi';

            return;
        }

        summaryElement.textContent = `Mostrati ${meta.from}-${meta.to} di ${meta.total} corsi`;
    };

    const toggleCourse = (course) => {
        const existingCourse = state.draftCourses.find((selectedCourse) => Number(selectedCourse.id) === Number(course.id));

        if (existingCourse) {
            state.draftCourses = normalizeCourses(
                state.draftCourses.filter((selectedCourse) => Number(selectedCourse.id) !== Number(course.id)),
            );
            renderDraftCourses();
            void loadCourses();

            return;
        }

        state.draftCourses = normalizeCourses([
            ...state.draftCourses,
            {
                ...course,
                sort_order: state.draftCourses.length + 1,
            },
        ]);
        renderDraftCourses();
        void loadCourses();
    };

    const renderRows = (rows) => {
        tbody.innerHTML = '';

        rows.forEach((row) => {
            const fragment = rowTemplate.content.cloneNode(true);
            const actionButton = fragment.querySelector('[data-action="toggle-course"]');
            const isSelected = state.draftCourses.some((course) => Number(course.id) === Number(row.id));

            fragment.querySelector('[data-cell="id"]').textContent = row.id;
            fragment.querySelector('[data-cell="title"]').textContent = row.title;
            fragment.querySelector('[data-cell="type"]').textContent = row.type.label;
            fragment.querySelector('[data-cell="status"]').textContent = row.status.label;
            fragment.querySelector('[data-cell="year"]').textContent = row.year ?? '-';
            actionButton.textContent = isSelected ? 'Rimuovi' : 'Aggiungi';
            actionButton.classList.toggle('btn-error', isSelected);
            actionButton.classList.toggle('btn-primary', !isSelected);

            actionButton.addEventListener('click', () => {
                toggleCourse(row);
            });

            tbody.appendChild(fragment);
        });
    };

    const loadCourses = async () => {
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

            renderRows(rows);
            renderSummary(meta);
            renderPagination(meta);
            emptyState.classList.toggle('hidden', rows.length > 0);
        } catch {
            tbody.innerHTML = '';
            emptyState.classList.remove('hidden');
            summaryElement.textContent = 'Errore nel caricamento dei corsi.';
            paginationContainer.innerHTML = '';
        } finally {
            setLoadingState(false);
        }
    };

    openModalButton.addEventListener('click', async () => {
        state.draftCourses = normalizeCourses(cloneCourses(state.selectedCourses));
        renderDraftCourses();
        modal.showModal();
        await loadCourses();
    });

    closeModalButton.addEventListener('click', () => {
        modal.close();
    });

    modal.addEventListener('close', () => {
        if (state.submitting) {
            state.submitting = false;

            return;
        }

        state.draftCourses = normalizeCourses(cloneCourses(state.selectedCourses));
        renderDraftCourses();
    });

    confirmButton.addEventListener('click', () => {
        state.selectedCourses = normalizeCourses(cloneCourses(state.draftCourses));
        syncHiddenInputs();
        renderCommittedCourses();
        state.submitting = true;
        form.requestSubmit();
    });

    searchButton.addEventListener('click', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        void loadCourses();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        state.search = searchInput.value.trim();
        state.page = 1;
        void loadCourses();
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const selectedSort = button.dataset.trainingPathCoursesSortKey;

            if (state.sort !== selectedSort) {
                state.sort = selectedSort;
                state.direction = 'asc';
            } else {
                state.direction = cycleSortDirection(state.direction);
            }

            state.page = 1;
            void loadCourses();
        });
    });

    state.draftCourses = normalizeCourses(cloneCourses(state.selectedCourses));
    renderCommittedCourses();
    renderDraftCourses();
}

function initializeRecipients(scope) {
    const form = scope.querySelector('[data-training-path-recipients-form]');

    if (!form) {
        return;
    }

    const status = form.querySelector('[data-training-path-recipients-status]');
    let saveTimer = null;

    const setStatus = (message, className = 'text-base-content/70') => {
        if (!status) {
            return;
        }

        status.textContent = message;
        status.className = `text-sm ${className}`;
        status.classList.remove('hidden');
    };

    const save = async () => {
        window.clearTimeout(saveTimer);
        setStatus('Salvataggio...', 'text-base-content/70');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                },
            });

            if (!response.ok) {
                throw new Error('Save failed');
            }

            setStatus('Salvato', 'text-success');
            saveTimer = window.setTimeout(() => status?.classList.add('hidden'), 1500);
        } catch {
            setStatus('Errore durante il salvataggio. Usa il pulsante Salva.', 'text-error');
        }
    };

    form.querySelectorAll('[data-auto-submit]').forEach((input) => {
        input.addEventListener('change', () => {
            void save();
        });
    });

    form.querySelectorAll('[data-recipient-table]').forEach((table) => {
        const search = table.querySelector('[data-recipient-search]');
        const rows = Array.from(table.querySelectorAll('[data-recipient-row]'));
        const summary = table.querySelector('[data-recipient-summary]');
        const empty = table.querySelector('[data-recipient-empty]');
        const previous = table.querySelector('[data-recipient-prev]');
        const next = table.querySelector('[data-recipient-next]');
        const pageSize = Number(table.dataset.pageSize || 10);
        let page = 1;

        const render = () => {
            const term = (search?.value || '').trim().toLowerCase();
            const filtered = rows.filter((row) => (row.dataset.recipientName || '').includes(term));
            const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
            page = Math.min(page, pages);
            const start = (page - 1) * pageSize;
            const visibleRows = new Set(filtered.slice(start, start + pageSize));

            rows.forEach((row) => {
                row.classList.toggle('hidden', !visibleRows.has(row));
            });

            if (summary) {
                summary.textContent = filtered.length === 0
                    ? '0 risultati'
                    : `${start + 1}-${Math.min(start + pageSize, filtered.length)} di ${filtered.length}`;
            }

            empty?.classList.toggle('hidden', filtered.length !== 0);
            previous?.toggleAttribute('disabled', page <= 1);
            next?.toggleAttribute('disabled', page >= pages);
        };

        search?.addEventListener('input', () => {
            page = 1;
            render();
        });
        previous?.addEventListener('click', () => {
            page = Math.max(1, page - 1);
            render();
        });
        next?.addEventListener('click', () => {
            page += 1;
            render();
        });

        render();
    });
}

function initializeEnrollments(scope) {
    const container = scope.querySelector('[data-training-path-enrollments-table]');

    if (!container) {
        return;
    }

    const apiUrl = container.dataset.trainingPathEnrollmentsApiUrl;
    const searchUsersApiUrl = container.dataset.trainingPathEnrollmentsSearchUsersApiUrl;
    const storeApiUrl = container.dataset.trainingPathEnrollmentsStoreApiUrl;
    const tbody = container.querySelector('[data-training-path-enrollments-tbody]');
    const template = container.querySelector('[data-training-path-enrollment-row-template]');
    const emptyState = container.querySelector('[data-training-path-enrollments-empty]');
    const tableContainer = container.querySelector('[data-training-path-enrollments-table-container]');
    const tableLoader = container.querySelector('[data-training-path-enrollments-loader]');
    const searchInput = container.querySelector('[data-training-path-enrollments-search]');
    const searchButton = container.querySelector('[data-training-path-enrollments-search-button]');
    const showTrashedCheckbox = container.querySelector('[data-training-path-enrollments-show-trashed]');
    const paginationContainer = container.querySelector('[data-training-path-enrollments-pagination]');
    const summaryElement = container.querySelector('[data-training-path-enrollments-summary]');
    const sortButtons = Array.from(container.querySelectorAll('[data-training-path-sort-key]'));
    const openCreateModalButton = container.querySelector('[data-open-training-path-enrollment-modal]');
    const createModal = container.querySelector('[data-create-training-path-enrollment-modal]');
    const closeCreateModalButton = container.querySelector('[data-close-create-training-path-enrollment-modal]');
    const userSearchInput = container.querySelector('[data-training-path-enrollment-user-search]');
    const userSearchButton = container.querySelector('[data-training-path-enrollment-user-search-button]');
    const userResultsBody = container.querySelector('[data-training-path-enrollment-user-results]');
    const userResultsEmptyState = container.querySelector('[data-training-path-enrollment-user-results-empty]');
    const userRowTemplate = container.querySelector('[data-training-path-enrollment-user-row-template]');
    const confirmModal = container.querySelector('[data-confirm-training-path-enrollment-modal]');
    const confirmMessage = container.querySelector('[data-confirm-training-path-enrollment-message]');
    const confirmSubmitButton = container.querySelector('[data-confirm-training-path-enrollment-submit]');

    if (!apiUrl || !searchUsersApiUrl || !storeApiUrl || !tbody || !template || !emptyState || !tableContainer || !tableLoader || !searchInput || !searchButton || !showTrashedCheckbox || !paginationContainer || !summaryElement || !openCreateModalButton || !(createModal instanceof HTMLDialogElement) || !closeCreateModalButton || !userSearchInput || !userSearchButton || !userResultsBody || !userResultsEmptyState || !userRowTemplate || !(confirmModal instanceof HTMLDialogElement) || !confirmMessage || !confirmSubmitButton) {
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
                    void loadEnrollments();
                });
            }

            return button;
        };

        paginationContainer.appendChild(createPageButton('<', Math.max(meta.current_page - 1, 1), meta.current_page <= 1));

        const pagesToRender = new Set([
            1,
            meta.last_page,
            Math.max(meta.current_page - 1, 1),
            meta.current_page,
            Math.min(meta.current_page + 1, meta.last_page),
        ]);

        Array.from(pagesToRender)
            .sort((first, second) => first - second)
            .forEach((page) => {
                paginationContainer.appendChild(createPageButton(String(page), page, false, page === meta.current_page));
            });

        paginationContainer.appendChild(createPageButton('>', Math.min(meta.current_page + 1, meta.last_page), meta.current_page >= meta.last_page));
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
            fragment.querySelector('[data-cell="status"]').textContent = row.status.label || '-';
            fragment.querySelector('[data-cell="progress"]').textContent = `${row.completion_percentage}% (${row.completed_courses}/${row.total_courses})`;
            fragment.querySelector('[data-cell="assigned_at"]').textContent = row.assigned_at || '-';

            deleteButton.classList.toggle('hidden', !row.actions.can_delete);
            restoreButton.classList.toggle('hidden', !row.actions.can_restore);

            if (row.actions.can_delete) {
                deleteButton.addEventListener('click', async () => {
                    if (!window.confirm('Sei sicuro di voler rimuovere questo iscritto dal percorso?')) {
                        return;
                    }

                    try {
                        await window.axios.delete(row.actions.delete_url, {
                            headers: { Accept: 'application/json' },
                        });

                        await loadEnrollments();
                    } catch (error) {
                        window.alert(error.response?.data?.message || 'Errore durante la rimozione dell\'iscritto.');
                    }
                });
            }

            if (row.actions.can_restore) {
                restoreButton.addEventListener('click', async () => {
                    if (!window.confirm('Vuoi ripristinare questo iscritto del percorso?')) {
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
                        window.alert(error.response?.data?.message || 'Errore durante il ripristino dell\'iscritto.');
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
                confirmMessage.textContent = `Confermi l'iscrizione di ${user.surname || ''} ${user.name || ''} (ID ${user.id}) al percorso formativo?`.trim();
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
        } catch {
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
        } catch {
            tbody.innerHTML = '';
            emptyState.classList.remove('hidden');
            summaryElement.textContent = 'Errore nel caricamento degli iscritti.';
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
        void searchUsers();
    });

    userSearchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        void searchUsers();
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
            await loadEnrollments();
        } catch (error) {
            const responseData = error.response?.data;

            if (error.response?.status === 409 && responseData?.requires_restore && responseData?.restore_url) {
                state.confirmAction = 'restore';
                state.restoreUrl = responseData.restore_url;
                confirmMessage.textContent = responseData.message || 'Esiste gia una iscrizione eliminata. Vuoi ripristinarla?';
            } else {
                window.alert(responseData?.message || 'Errore durante l\'iscrizione al percorso.');
            }
        } finally {
            setButtonLoading(confirmSubmitButton, false);
        }
    });

    searchButton.addEventListener('click', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        void loadEnrollments();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        state.search = searchInput.value.trim();
        state.page = 1;
        void loadEnrollments();
    });

    showTrashedCheckbox.addEventListener('change', () => {
        state.showTrashed = showTrashedCheckbox.checked;
        state.page = 1;
        void loadEnrollments();
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const selectedSort = button.dataset.trainingPathSortKey;

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
            void loadEnrollments();
        });
    });

    void loadEnrollments();
}
