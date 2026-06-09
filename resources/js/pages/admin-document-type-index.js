document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-document-types-page]');

    if (!page) {
        return;
    }

    const indexUrl = page.dataset.indexUrl;
    const tbody = page.querySelector('[data-document-types-tbody]');
    const emptyState = page.querySelector('[data-document-types-empty]');
    const summary = page.querySelector('[data-document-types-summary]');
    const pagination = page.querySelector('[data-document-types-pagination]');
    const loading = page.querySelector('[data-document-types-loading]');
    const searchInput = page.querySelector('[data-document-types-search]');
    const searchButton = page.querySelector('[data-document-types-search-button]');
    const showTrashedCheckbox = page.querySelector('[data-document-types-show-trashed]');
    const sortButtons = page.querySelectorAll('[data-sort-key]');

    if (!indexUrl || !tbody || !emptyState || !summary || !pagination || !loading || !searchInput || !searchButton || !showTrashedCheckbox) {
        return;
    }

    const state = {
        page: 1,
        search: page.dataset.initialSearch || '',
        sort: page.dataset.initialSort || 'id',
        direction: page.dataset.initialDirection || 'desc',
        showTrashed: page.dataset.initialShowTrashed === '1',
        loading: false,
    };

    const escapeHtml = (value) => String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const syncUrl = () => {
        const params = new URLSearchParams();
        params.set('page', String(state.page));
        params.set('sort', state.sort);
        params.set('direction', state.direction);
        params.set('show_trashed', state.showTrashed ? '1' : '0');

        if (state.search !== '') {
            params.set('search', state.search);
        }

        window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
    };

    const updateSortIcons = () => {
        sortButtons.forEach((button) => {
            const sortKey = button.dataset.sortKey;
            const iconElement = page.querySelector(`[data-sort-icon="${sortKey}"]`);

            if (!iconElement) {
                return;
            }

            const isActive = state.sort === sortKey;

            if (!isActive) {
                iconElement.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-base-content/50"><path d="m21 16-4 4-4-4"/><path d="M17 20V4"/><path d="m3 8 4-4 4 4"/><path d="M7 4v16"/></svg>';
            } else if (state.direction === 'asc') {
                iconElement.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>';
            } else {
                iconElement.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
            }
        });
    };

    const setLoading = (isLoading) => {
        state.loading = isLoading;
        loading.classList.toggle('hidden', !isLoading);
        searchButton.disabled = isLoading;
        showTrashedCheckbox.disabled = isLoading;
        sortButtons.forEach((button) => {
            button.disabled = isLoading;
        });
    };

    const buildQueryString = () => {
        const params = new URLSearchParams({
            page: String(state.page),
            sort: state.sort,
            direction: state.direction,
            show_trashed: state.showTrashed ? '1' : '0',
        });

        if (state.search !== '') {
            params.set('search', state.search);
        }

        return params.toString();
    };

    const renderSummary = (meta) => {
        if (!meta || meta.total === 0 || meta.from === null || meta.to === null) {
            summary.textContent = '0 tipologie documento';

            return;
        }

        summary.textContent = `Visualizzazione di ${meta.from}-${meta.to} su ${meta.total} risultati`;
    };

    const renderPagination = (meta) => {
        pagination.innerHTML = '';

        if (!meta || meta.last_page <= 1) {
            return;
        }

        const createButton = (label, pageNumber, active = false, disabled = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `join-item btn ${active ? 'btn-active' : ''}`;
            button.textContent = label;

            if (disabled) {
                button.classList.add('btn-disabled', 'pointer-events-none');
            }

            if (!disabled) {
                button.addEventListener('click', () => {
                    state.page = pageNumber;
                    void loadDocumentTypes();
                });
            }

            return button;
        };

        pagination.appendChild(createButton('Precedente', Math.max(meta.current_page - 1, 1), false, meta.current_page <= 1));

        for (let pageNumber = 1; pageNumber <= meta.last_page; pageNumber += 1) {
            pagination.appendChild(createButton(String(pageNumber), pageNumber, pageNumber === meta.current_page));
        }

        pagination.appendChild(createButton('Successiva', Math.min(meta.current_page + 1, meta.last_page), false, meta.current_page >= meta.last_page));
    };

    const loadDocumentTypes = async () => {
        if (state.loading) {
            return;
        }

        setLoading(true);
        syncUrl();
        updateSortIcons();

        try {
            const response = await window.axios.get(`${indexUrl}?${buildQueryString()}`, {
                headers: { Accept: 'application/json' },
            });

            const rows = response.data.data ?? [];
            const meta = response.data.meta ?? null;

            tbody.innerHTML = '';

            rows.forEach((row) => {
                const tableRow = document.createElement('tr');
                tableRow.className = row.is_deleted ? 'opacity-70' : '';
                tableRow.innerHTML = `
                    <td>${row.id}</td>
                    <td>${escapeHtml(row.name)}</td>
                    <td>${escapeHtml(row.description || '-')}</td>
                    <td><span class="badge ${row.status_badge_class} h-fit">${escapeHtml(row.status_label)}</span></td>
                    <td>
                        <div class="flex justify-end gap-2">
                            <a href="${row.actions.edit_url}" class="btn btn-primary btn-sm">Modifica</a>
                            ${row.is_deleted
                                ? '<button type="button" class="btn btn-success btn-sm" data-action="restore">Ripristina</button>'
                                : '<button type="button" class="btn btn-error btn-sm" data-action="delete">Elimina</button>'}
                        </div>
                    </td>
                `;

                const deleteButton = tableRow.querySelector('[data-action="delete"]');
                const restoreButton = tableRow.querySelector('[data-action="restore"]');

                if (deleteButton) {
                    deleteButton.addEventListener('click', async () => {
                        if (!window.confirm('Sei sicuro di voler eliminare questa tipologia?')) {
                            return;
                        }

                        try {
                            await window.axios.delete(row.actions.delete_url, {
                                headers: { Accept: 'application/json' },
                            });

                            await loadDocumentTypes();
                            window.showFlash?.('success', 'Tipologia documento eliminata con successo.');
                        } catch (error) {
                            window.showFlash?.('error', error.response?.data?.message || 'Errore durante l\'eliminazione della tipologia documento.');
                        }
                    });
                }

                if (restoreButton) {
                    restoreButton.addEventListener('click', async () => {
                        try {
                            await window.axios.post(row.actions.restore_url, {}, {
                                headers: { Accept: 'application/json' },
                            });

                            await loadDocumentTypes();
                            window.showFlash?.('success', 'Tipologia documento ripristinata con successo.');
                        } catch (error) {
                            window.showFlash?.('error', error.response?.data?.message || 'Errore durante il ripristino della tipologia documento.');
                        }
                    });
                }

                tbody.appendChild(tableRow);
            });

            renderSummary(meta);
            renderPagination(meta);
            emptyState.classList.toggle('hidden', rows.length > 0);
        } catch (error) {
            tbody.innerHTML = '';
            emptyState.classList.remove('hidden');
            summary.textContent = 'Errore nel caricamento delle tipologie documento.';
            pagination.innerHTML = '';
        } finally {
            setLoading(false);
        }
    };

    searchInput.value = state.search;
    showTrashedCheckbox.checked = state.showTrashed;
    updateSortIcons();

    searchButton.addEventListener('click', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        void loadDocumentTypes();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        state.search = searchInput.value.trim();
        state.page = 1;
        void loadDocumentTypes();
    });

    showTrashedCheckbox.addEventListener('change', () => {
        state.showTrashed = showTrashedCheckbox.checked;
        state.page = 1;
        void loadDocumentTypes();
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const sortKey = button.dataset.sortKey;

            if (state.sort !== sortKey) {
                state.sort = sortKey;
                state.direction = sortKey === 'id' ? 'desc' : 'asc';
            } else {
                state.direction = state.direction === 'asc' ? 'desc' : 'asc';
            }

            state.page = 1;
            void loadDocumentTypes();
        });
    });

    void loadDocumentTypes();
});
