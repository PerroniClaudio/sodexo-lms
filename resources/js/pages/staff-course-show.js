document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('[data-staff-enrollments-table]');

    if (!container) {
        return;
    }

    initializeStaffEnrollmentsTable(container);
});

function initializeStaffEnrollmentsTable(container) {
    const apiUrl = container.dataset.enrollmentsApiUrl;
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

    if (!apiUrl || !tbody || !template || !emptyState || !searchInput || !searchButton || !showTrashedCheckbox || !paginationContainer || !summaryElement) {
        return;
    }

    const state = {
        page: 1,
        search: '',
        showTrashed: false,
        sort: 'surname',
        direction: 'asc',
        loading: false,
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

            fragment.querySelector('[data-cell="surname"]').textContent = row.user.surname || '-';
            fragment.querySelector('[data-cell="name"]').textContent = row.user.name || '-';
            fragment.querySelector('[data-cell="fiscal_code"]').textContent = row.user.fiscal_code || '-';
            fragment.querySelector('[data-cell="email"]').textContent = row.user.email || '-';
            fragment.querySelector('[data-cell="completion_percentage"]').textContent = `${row.completion_percentage ?? 0}%`;
            fragment.querySelector('[data-cell="assigned_at"]').textContent = row.assigned_at || '-';

            statusBadge.textContent = row.is_deleted ? 'Eliminato' : row.status.label;
            statusBadge.classList.add(buildStatusBadgeClass(row));

            if (row.is_deleted) {
                tr.classList.add('opacity-70');
            }

            tbody.appendChild(fragment);
        });
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
