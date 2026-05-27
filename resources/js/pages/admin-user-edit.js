document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-admin-user-edit-page]');

    if (!page) {
        return;
    }

    initializeRiskSummary(page);
    initializeUserEditForm(page);
    initializeCertificatesTable(page);
});

function initializeRiskSummary(page) {
    const container = page.querySelector('[data-risk-summary]');

    if (!container) {
        return;
    }

    const summaryUrl = container.dataset.riskSummaryUrl;
    const messageElement = container.querySelector('[data-risk-summary-message]');
    const badgeElement = container.querySelector('[data-risk-summary-badge]');
    const riskBasedRequirementsContainer = container.querySelector('[data-risk-based-requirements-items]');

    if (!summaryUrl || !messageElement || !badgeElement || !riskBasedRequirementsContainer) {
        return;
    }

    const escapeHtml = (value) => String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const requirementBadgeClass = (status) => {
        if (status === 'satisfied') {
            return 'badge-success badge-soft';
        }

        if (status === 'expired') {
            return 'badge-warning badge-soft';
        }

        return 'badge-error badge-soft';
    };

    const render = (summary) => {
        messageElement.textContent = summary.message || '';
        badgeElement.className = `badge badge-lg ${summary.risk_badge_class || 'badge-ghost'}`;
        badgeElement.textContent = summary.risk_label || 'Non applicabile';
        riskBasedRequirementsContainer.innerHTML = '';

        if (!Array.isArray(summary.risk_based_requirements) || summary.risk_based_requirements.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-sm text-base-content/70';
            empty.textContent = 'Nessun requisito di rischio disponibile.';
            riskBasedRequirementsContainer.appendChild(empty);

            return;
        }

        summary.risk_based_requirements.forEach((riskBasedRequirement) => {
            const item = document.createElement('div');
            item.className = 'rounded-box border border-base-300 bg-base-200/40 p-4';
            item.innerHTML = `
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-1">
                        <div class="font-semibold text-base-content">${escapeHtml(riskBasedRequirement.risk_based_requirement_name)}</div>
                        ${riskBasedRequirement.risk_based_requirement_description ? `<p class="text-sm text-base-content/70">${escapeHtml(riskBasedRequirement.risk_based_requirement_description)}</p>` : ''}
                    </div>
                    <span class="badge ${requirementBadgeClass(riskBasedRequirement.status)}">${escapeHtml(riskBasedRequirement.status_label)}</span>
                </div>
            `;
            riskBasedRequirementsContainer.appendChild(item);
        });
    };

    page.refreshRiskSummary = async () => {
        const response = await window.axios.get(summaryUrl, {
            headers: { Accept: 'application/json' },
        });

        render(response.data.data);
    };
}

function initializeUserEditForm(page) {
    const form = page.querySelector('[data-user-edit-form]');
    const successAlert = page.querySelector('[data-user-form-success]');
    const errorAlert = page.querySelector('[data-user-form-error]');

    if (!form || !successAlert || !errorAlert) {
        return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    const refreshRiskSummarySafely = async () => {
        if (typeof page.refreshRiskSummary !== 'function') {
            return;
        }

        try {
            await page.refreshRiskSummary();
        } catch (error) {
            console.error('Unable to refresh risk summary after user update.', error);
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        successAlert.classList.add('hidden');
        successAlert.textContent = '';
        errorAlert.classList.add('hidden');
        errorAlert.textContent = '';
        submitButton.disabled = true;

        try {
            const formData = new FormData(form);
            const response = await window.axios.post(form.action, formData, {
                headers: {
                    Accept: 'application/json',
                },
            });

            successAlert.textContent = response.data.message || 'Utente aggiornato con successo';
            successAlert.classList.remove('hidden');
            await refreshRiskSummarySafely();
        } catch (error) {
            const message = error.response?.data?.message
                || Object.values(error.response?.data?.errors || {}).flat().join(' ')
                || 'Errore durante il salvataggio dell\'utente.';

            errorAlert.textContent = message;
            errorAlert.classList.remove('hidden');
        } finally {
            submitButton.disabled = false;
        }
    });
}

function initializeCertificatesTable(page) {
    const container = page.querySelector('[data-user-certificates]');

    if (!container) {
        return;
    }

    const apiUrl = container.dataset.indexUrl;
    const storeUrl = container.dataset.storeUrl;
    const tableBody = container.querySelector('[data-certificates-tbody]');
    const emptyState = container.querySelector('[data-certificates-empty]');
    const summary = container.querySelector('[data-certificates-summary]');
    const pagination = container.querySelector('[data-certificates-pagination]');
    const searchInput = container.querySelector('[data-certificates-search-input]');
    const searchButton = container.querySelector('[data-certificates-search-button]');
    const loadingIndicator = container.querySelector('[data-certificates-loading]');
    const sortButtons = container.querySelectorAll('[data-sort-key]');
    const modal = container.querySelector('[data-certificate-modal]');
    const openModalButton = container.querySelector('[data-open-certificate-modal]');
    const closeButtons = container.querySelectorAll('[data-close-certificate-modal]');
    const form = container.querySelector('[data-certificate-form]');
    const formError = container.querySelector('[data-certificate-form-error]');
    const submitButton = form?.querySelector('button[type="submit"]');
    const riskBasedRequirementsSelect = form?.querySelector('[data-risk-based-requirements-select]');
    const certificateIdInput = form?.elements.namedItem('certificate_id');
    const modalTitle = container.querySelector('[data-certificate-modal] h3');

    if (!apiUrl || !storeUrl || !tableBody || !emptyState || !summary || !pagination || !searchInput || !searchButton || !loadingIndicator || !modal || !openModalButton || closeButtons.length === 0 || !form || !formError || !submitButton || !riskBasedRequirementsSelect || !certificateIdInput || !modalTitle) {
        return;
    }

    const state = {
        page: 1,
        search: '',
        sort: 'issued_at',
        direction: 'desc',
        loading: false,
        editingCertificate: null,
    };

    const escapeHtml = (value) => String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    const refreshRiskSummarySafely = async () => {
        if (typeof page.refreshRiskSummary !== 'function') {
            return;
        }

        try {
            await page.refreshRiskSummary();
        } catch (error) {
            console.error('Unable to refresh risk summary after certificate mutation.', error);
        }
    };

    const setLoading = (loading) => {
        state.loading = loading;
        loadingIndicator.classList.toggle('hidden', !loading);
        searchButton.disabled = loading;
        sortButtons.forEach((button) => {
            button.disabled = loading;
        });
    };

    const buildQueryString = () => {
        const params = new URLSearchParams({
            page: String(state.page),
            sort: state.sort,
            direction: state.direction,
        });

        if (state.search !== '') {
            params.set('search', state.search);
        }

        return params.toString();
    };

    const renderRiskBasedRequirements = (riskBasedRequirements) => {
        if (!Array.isArray(riskBasedRequirements) || riskBasedRequirements.length === 0) {
            return '<span class="text-sm text-base-content/50">-</span>';
        }

        return riskBasedRequirements
            .map((riskBasedRequirement) => `<span class="badge badge-outline badge-sm">${escapeHtml(riskBasedRequirement.name)}</span>`)
            .join(' ');
    };

    const renderRows = (rows) => {
        tableBody.innerHTML = '';

        rows.forEach((row) => {
            const tableRow = document.createElement('tr');
            tableRow.innerHTML = `
                <td>
                    <div class="font-medium">${escapeHtml(row.name)}</div>
                    <div class="text-xs text-base-content/60">${escapeHtml(row.description || '')}</div>
                </td>
                <td>${escapeHtml(row.issued_at || '-')}</td>
                <td>${escapeHtml(row.expires_at || '-')}</td>
                <td>
                    <span class="badge ${row.is_internal ? 'badge-success badge-soft' : 'badge-neutral badge-soft'}">
                        ${escapeHtml(row.type_label)}
                    </span>
                    ${row.internal_course ? `<div class="mt-1 text-xs text-base-content/60">${escapeHtml(row.internal_course)}</div>` : ''}
                </td>
                <td class="max-w-md">
                    <div class="flex flex-wrap gap-1">${renderRiskBasedRequirements(row.risk_based_requirements)}</div>
                </td>
                <td>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost btn-sm" data-action="edit">Modifica</button>
                        <button type="button" class="btn btn-error btn-outline btn-sm" data-action="delete">Elimina</button>
                    </div>
                </td>
            `;
            tableRow.querySelector('[data-action="edit"]').addEventListener('click', () => {
                openEditModal(row);
            });
            tableRow.querySelector('[data-action="delete"]').addEventListener('click', async () => {
                await deleteCertificate(row);
            });
            tableBody.appendChild(tableRow);
        });
    };

    const renderSummary = (meta) => {
        if (!meta || meta.total === 0 || meta.from === null || meta.to === null) {
            summary.textContent = '0 certificati';

            return;
        }

        summary.textContent = `Mostrati ${meta.from}-${meta.to} di ${meta.total} certificati`;
    };

    const renderPagination = (meta) => {
        pagination.innerHTML = '';

        if (!meta || meta.last_page <= 1) {
            return;
        }

        for (let pageNumber = 1; pageNumber <= meta.last_page; pageNumber += 1) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `join-item btn btn-sm ${pageNumber === meta.current_page ? 'btn-active' : ''}`;
            button.textContent = String(pageNumber);
            button.addEventListener('click', () => {
                if (state.page === pageNumber) {
                    return;
                }

                state.page = pageNumber;
                loadCertificates();
            });
            pagination.appendChild(button);
        }
    };

    const loadCertificates = async () => {
        if (state.loading) {
            return;
        }

        setLoading(true);

        try {
            const response = await window.axios.get(`${apiUrl}?${buildQueryString()}`, {
                headers: { Accept: 'application/json' },
            });

            const rows = response.data.data ?? [];
            const meta = response.data.meta ?? null;

            renderRows(rows);
            renderSummary(meta);
            renderPagination(meta);
            emptyState.classList.toggle('hidden', rows.length > 0);
        } catch (error) {
            tableBody.innerHTML = '';
            emptyState.classList.remove('hidden');
            summary.textContent = 'Errore nel caricamento dei certificati.';
            pagination.innerHTML = '';
        } finally {
            setLoading(false);
        }
    };

    const resetForm = () => {
        state.editingCertificate = null;
        form.reset();
        certificateIdInput.value = '';
        modalTitle.textContent = 'Aggiungi certificato';
        submitButton.textContent = 'Salva certificato';
        formError.classList.add('hidden');
        formError.textContent = '';
        Array.from(riskBasedRequirementsSelect.options).forEach((option) => {
            option.selected = false;
        });
    };

    const openEditModal = (certificate) => {
        resetForm();
        state.editingCertificate = certificate;
        certificateIdInput.value = String(certificate.id);
        modalTitle.textContent = 'Modifica certificato';
        submitButton.textContent = 'Aggiorna certificato';
        form.elements.namedItem('name').value = certificate.name || '';
        form.elements.namedItem('description').value = certificate.description || '';
        form.elements.namedItem('file_path').value = certificate.file_path || '';
        form.elements.namedItem('issued_at').value = certificate.issued_at_iso || '';
        form.elements.namedItem('expires_at').value = certificate.expires_at_iso || '';
        form.elements.namedItem('internal_course_id').value = certificate.internal_course_id || '';

        const riskBasedRequirementIds = new Set((certificate.risk_based_requirements || []).map((riskBasedRequirement) => Number(riskBasedRequirement.id)));
        Array.from(riskBasedRequirementsSelect.options).forEach((option) => {
            option.selected = riskBasedRequirementIds.has(Number(option.value));
        });

        modal.showModal();
    };

    const deleteCertificate = async (certificate) => {
        if (!window.confirm(`Eliminare il certificato "${certificate.name}"?`)) {
            return;
        }

        try {
            await window.axios.delete(certificate.actions.delete_url, {
                headers: { Accept: 'application/json' },
            });

            await loadCertificates();
            await refreshRiskSummarySafely();
        } catch (error) {
            window.alert(error.response?.data?.message || 'Errore durante l\'eliminazione del certificato.');
        }
    };

    openModalButton.addEventListener('click', () => {
        resetForm();
        modal.showModal();
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            modal.close();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        formError.classList.add('hidden');
        formError.textContent = '';
        submitButton.disabled = true;

        const formData = new FormData(form);
        const payload = {
            name: formData.get('name'),
            description: formData.get('description') || null,
            file_path: formData.get('file_path') || null,
            issued_at: formData.get('issued_at'),
            expires_at: formData.get('expires_at') || null,
            internal_course_id: formData.get('internal_course_id') || null,
            risk_based_requirement_ids: Array.from(riskBasedRequirementsSelect.selectedOptions).map((option) => Number(option.value)),
        };

        try {
            if (state.editingCertificate?.actions?.update_url) {
                await window.axios.put(state.editingCertificate.actions.update_url, payload, {
                    headers: { Accept: 'application/json' },
                });
            } else {
                await window.axios.post(storeUrl, payload, {
                    headers: { Accept: 'application/json' },
                });
            }

            modal.close();
            state.page = 1;
            await loadCertificates();
            await refreshRiskSummarySafely();
        } catch (error) {
            const message = error.response?.data?.message
                || Object.values(error.response?.data?.errors || {}).flat().join(' ')
                || 'Errore durante il salvataggio del certificato.';

            formError.textContent = message;
            formError.classList.remove('hidden');
        } finally {
            submitButton.disabled = false;
        }
    });

    searchButton.addEventListener('click', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        loadCertificates();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        state.search = searchInput.value.trim();
        state.page = 1;
        loadCertificates();
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const sortKey = button.dataset.sortKey;

            if (state.sort !== sortKey) {
                state.sort = sortKey;
                state.direction = 'asc';
            } else {
                state.direction = state.direction === 'asc' ? 'desc' : 'asc';
            }

            state.page = 1;
            loadCertificates();
        });
    });

    loadCertificates();
}
