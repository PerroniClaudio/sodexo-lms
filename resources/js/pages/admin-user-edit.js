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

    const updateContainerAppearance = (riskBadgeClass) => {
        container.classList.remove(
            'border-base-300',
            'bg-base-100',
            'border-success/40',
            'bg-success/5',
            'border-warning/40',
            'bg-warning/5',
            'border-error/40',
            'bg-error/5',
        );

        if (riskBadgeClass === 'badge-success') {
            container.classList.add('border-success/40', 'bg-success/5');

            return;
        }

        if (riskBadgeClass === 'badge-warning') {
            container.classList.add('border-warning/40', 'bg-warning/5');

            return;
        }

        if (riskBadgeClass === 'badge-error') {
            container.classList.add('border-error/40', 'bg-error/5');

            return;
        }

        container.classList.add('border-base-300', 'bg-base-100');
    };

    const render = (summary) => {
        messageElement.textContent = summary.message || '';
        badgeElement.className = `badge badge-lg ${summary.risk_badge_class || 'badge-ghost'}`;
        badgeElement.textContent = summary.risk_label || 'Non applicabile';
        updateContainerAppearance(summary.risk_badge_class || 'badge-ghost');
        riskBasedRequirementsContainer.innerHTML = '';

        if (!Array.isArray(summary.risk_based_requirements) || summary.risk_based_requirements.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-sm text-base-content/70';
            empty.textContent = 'Nessun requisito di rischio disponibile.';
            riskBasedRequirementsContainer.appendChild(empty);

            return;
        }

        summary.risk_based_requirements.forEach((riskBasedRequirement) => {
            const requiredTypeLabel = riskBasedRequirement.required_course_validity_type_label
                ? String(riskBasedRequirement.required_course_validity_type_label).toLowerCase()
                : '';
            const item = document.createElement('div');
            item.className = 'rounded-box border border-base-300 bg-base-200/40 p-4';
            item.innerHTML = `
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-1">
                        <div class="font-semibold text-base-content">${escapeHtml(riskBasedRequirement.risk_based_requirement_name)}</div>
                        ${riskBasedRequirement.risk_based_requirement_description ? `<p class="text-sm text-base-content/70">${escapeHtml(riskBasedRequirement.risk_based_requirement_description)}</p>` : ''}
                    </div>
                    <div class="flex flex-col items-start gap-2 md:items-end">
                        <span class="badge ${requirementBadgeClass(riskBasedRequirement.status)}">${escapeHtml(riskBasedRequirement.status_label)}</span>
                        ${['missing', 'expired'].includes(riskBasedRequirement.status) && requiredTypeLabel !== ''
                            ? `<p class="text-sm text-base-content/70">Richiesto: ${escapeHtml(requiredTypeLabel)}</p>`
                            : ''}
                    </div>
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

    if (!form) {
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

        submitButton.disabled = true;

        try {
            const formData = new FormData(form);
            const response = await window.axios.post(form.action, formData, {
                headers: {
                    Accept: 'application/json',
                },
            });

            window.showFlash?.('success', response.data.message || 'Utente aggiornato con successo');
            await refreshRiskSummarySafely();
        } catch (error) {
            const message = error.response?.data?.message
                || Object.values(error.response?.data?.errors || {}).flat().join(' ')
                || 'Errore durante il salvataggio dell\'utente.';

            window.showFlash?.('error', message);
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
    const submitButton = form?.querySelector('button[type="submit"]');
    const riskBasedRequirementsSelect = form?.querySelector('[data-risk-based-requirements-select]');
    const certificateIdInput = form?.elements.namedItem('certificate_id');
    const modalTitle = container.querySelector('[data-certificate-modal] h3');
    const documentTypeSelect = form?.elements.namedItem('document_type_id');
    const filesSelection = container.querySelector('[data-certificate-files-selection]');
    const filesInput = container.querySelector('[data-certificate-files-input]');
    const filesDropzone = container.querySelector('[data-certificate-files-dropzone]');
    const filesCreateHint = container.querySelector('[data-certificate-files-create-hint]');
    const existingFilesContainer = container.querySelector('[data-certificate-existing-files]');
    const filesSummary = container.querySelector('[data-certificate-files-summary]');
    const filesLoading = container.querySelector('[data-certificate-files-loading]');
    const filesTableBody = container.querySelector('[data-certificate-files-tbody]');
    const filesEmptyState = container.querySelector('[data-certificate-files-empty]');
    const showDeletedFilesCheckbox = container.querySelector('[data-certificate-files-show-deleted]');

    if (!apiUrl || !storeUrl || !tableBody || !emptyState || !summary || !pagination || !searchInput || !searchButton || !loadingIndicator || !modal || !openModalButton || closeButtons.length === 0 || !form || !submitButton || !riskBasedRequirementsSelect || !certificateIdInput || !modalTitle || !documentTypeSelect || !filesSelection || !filesInput || !filesDropzone || !filesCreateHint || !existingFilesContainer || !filesSummary || !filesLoading || !filesTableBody || !filesEmptyState || !showDeletedFilesCheckbox) {
        return;
    }

    const state = {
        page: 1,
        search: '',
        sort: 'issued_at',
        direction: 'desc',
        loading: false,
        loadingFiles: false,
        editingCertificate: null,
        showDeletedFiles: false,
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
    const formatFilesSummary = (activeCount, totalCount) => {
        if (totalCount === 0) {
            return 'Nessun file caricato.';
        }

        if (activeCount === totalCount) {
            return `${activeCount} file attivi`;
        }

        return `${activeCount} file attivi su ${totalCount} totali`;
    };

    const ensureDocumentTypeOption = (certificate) => {
        const currentValue = certificate?.document_type_id;

        Array.from(documentTypeSelect.options)
            .filter((option) => option.dataset.dynamicDeleted === 'true')
            .forEach((option) => {
                if (String(option.value) !== String(currentValue || '')) {
                    option.remove();
                }
            });

        if (!currentValue) {
            return;
        }

        const existingOption = Array.from(documentTypeSelect.options)
            .find((option) => String(option.value) === String(currentValue));

        if (existingOption) {
            return;
        }

        const option = document.createElement('option');
        option.value = String(currentValue);
        option.textContent = certificate.document_type_is_deleted
            ? `${certificate.document_type_name || 'Tipologia documento'} (eliminata)`
            : (certificate.document_type_name || 'Tipologia documento');
        option.dataset.dynamicDeleted = 'true';
        documentTypeSelect.appendChild(option);
    };

    const updateSelectedFilesLabel = () => {
        const selectedFiles = Array.from(filesInput.files || []);

        if (selectedFiles.length === 0) {
            filesSelection.textContent = 'Nessun file selezionato';

            return;
        }

        filesSelection.textContent = selectedFiles.map((file) => file.name).join(', ');
    };

    const toggleExistingFilesVisibility = (visible) => {
        existingFilesContainer.classList.toggle('hidden', !visible);
        filesCreateHint.classList.toggle('hidden', visible);
    };

    const setFilesLoading = (loading) => {
        state.loadingFiles = loading;
        filesLoading.classList.toggle('hidden', !loading);
        showDeletedFilesCheckbox.disabled = loading;
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

    const openPreview = (url) => {
        window.open(url, '_blank', 'noopener');
    };

    const downloadFile = (url) => {
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        link.remove();
    };

    const renderLatestFile = (row) => {
        if (!row.latest_active_file) {
            return '<span class="text-sm text-base-content/50">-</span>';
        }

        const file = row.latest_active_file;

        return `
            <div class="space-y-1">
                <div class="font-medium">${escapeHtml(file.original_name)}</div>
                <div class="text-xs text-base-content/60">${escapeHtml(formatFilesSummary(row.active_files_count || 0, row.total_files_count || 0))}</div>
            </div>
        `;
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
                <td>
                    ${row.document_type_name
                        ? `<span class="badge badge-outline">${escapeHtml(row.document_type_name)}${row.document_type_is_deleted ? ' (eliminata)' : ''}</span>`
                        : '<span class="text-sm text-base-content/50">-</span>'}
                </td>
                <td>${renderLatestFile(row)}</td>
                <td class="max-w-md">
                    <div class="flex flex-wrap gap-1">${renderRiskBasedRequirements(row.risk_based_requirements)}</div>
                </td>
                <td>
                    <div class="ml-auto inline-grid grid-cols-[max-content_max-content] gap-2">
                        <button type="button" class="btn btn-primary btn-sm whitespace-nowrap" data-action="edit">Modifica</button>
                        <button type="button" class="btn btn-error btn-outline btn-sm whitespace-nowrap" data-action="delete">Elimina</button>
                        <button
                            type="button"
                            class="btn btn-primary btn-outline btn-sm whitespace-nowrap"
                            data-action="preview-latest"
                            ${row.latest_active_file?.actions?.preview_url ? '' : 'disabled'}
                        >
                            Anteprima
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary btn-outline btn-sm whitespace-nowrap"
                            data-action="download-latest"
                            ${row.latest_active_file?.actions?.download_url ? '' : 'disabled'}
                        >
                            Scarica
                        </button>
                    </div>
                </td>
            `;

            const previewLatestButton = tableRow.querySelector('[data-action="preview-latest"]');
            const downloadLatestButton = tableRow.querySelector('[data-action="download-latest"]');

            if (previewLatestButton && row.latest_active_file?.actions?.preview_url) {
                previewLatestButton.addEventListener('click', () => {
                    openPreview(row.latest_active_file.actions.preview_url);
                });
            }

            if (downloadLatestButton && row.latest_active_file?.actions?.download_url) {
                downloadLatestButton.addEventListener('click', () => {
                    downloadFile(row.latest_active_file.actions.download_url);
                });
            }

            tableRow.querySelector('[data-action="edit"]').addEventListener('click', () => {
                void openEditModal(row);
            });

            tableRow.querySelector('[data-action="delete"]').addEventListener('click', async () => {
                await deleteCertificate(row);
            });

            tableBody.appendChild(tableRow);
        });
    };

    const renderCertificateFiles = (files, meta = null) => {
        filesTableBody.innerHTML = '';
        filesSummary.textContent = formatFilesSummary(meta?.active_files_count ?? 0, meta?.total_files_count ?? 0);
        filesEmptyState.classList.toggle('hidden', files.length > 0);

        files.forEach((file) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="font-medium">${escapeHtml(file.original_name)}</div>
                    <div class="text-xs text-base-content/60">${escapeHtml(file.size_label || '')}</div>
                </td>
                <td>${escapeHtml(file.uploaded_at || '-')}</td>
                <td>
                    <span class="badge ${file.is_deleted ? 'badge-outline badge-warning' : 'badge-outline badge-success'}">
                        ${file.is_deleted ? 'Eliminato' : 'Attivo'}
                    </span>
                    ${file.deleted_at ? `<div class="mt-1 text-xs text-base-content/60">Soft delete: ${escapeHtml(file.deleted_at)}</div>` : ''}
                </td>
                <td>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost btn-sm" data-action="preview">Anteprima</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-action="download">Scarica</button>
                        ${file.is_deleted ? '' : '<button type="button" class="btn btn-error btn-outline btn-sm" data-action="delete">Elimina</button>'}
                    </div>
                </td>
            `;

            row.querySelector('[data-action="preview"]').addEventListener('click', () => {
                openPreview(file.actions.preview_url);
            });

            row.querySelector('[data-action="download"]').addEventListener('click', () => {
                downloadFile(file.actions.download_url);
            });

            const deleteButton = row.querySelector('[data-action="delete"]');

            if (deleteButton) {
                deleteButton.addEventListener('click', async () => {
                    if (!window.confirm(`Eliminare il file "${file.original_name}"?`)) {
                        return;
                    }

                    try {
                        await window.axios.delete(file.actions.delete_url, {
                            headers: { Accept: 'application/json' },
                        });

                        await loadCertificateFiles();
                        await loadCertificates();
                        window.showFlash?.('success', 'File certificato eliminato con successo');
                    } catch (error) {
                        window.showFlash?.('error', error.response?.data?.message || 'Errore durante l\'eliminazione del file.');
                    }
                });
            }

            filesTableBody.appendChild(row);
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
                void loadCertificates();
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

    const loadCertificateFiles = async () => {
        if (!state.editingCertificate?.actions?.files_index_url || state.loadingFiles) {
            return;
        }

        setFilesLoading(true);

        try {
            const response = await window.axios.get(state.editingCertificate.actions.files_index_url, {
                params: {
                    show_deleted_files: state.showDeletedFiles ? '1' : '0',
                },
                headers: { Accept: 'application/json' },
            });

            renderCertificateFiles(response.data.data ?? [], response.data.meta ?? null);
        } catch (error) {
            filesTableBody.innerHTML = '';
            filesEmptyState.classList.remove('hidden');
            filesSummary.textContent = 'Errore nel caricamento dei file.';
        } finally {
            setFilesLoading(false);
        }
    };

    const resetForm = () => {
        state.editingCertificate = null;
        state.showDeletedFiles = false;
        form.reset();
        certificateIdInput.value = '';
        modalTitle.textContent = 'Aggiungi certificato';
        submitButton.textContent = 'Salva certificato';
        showDeletedFilesCheckbox.checked = false;
        filesTableBody.innerHTML = '';
        filesSummary.textContent = 'Nessun file caricato.';
        filesEmptyState.classList.remove('hidden');
        Array.from(documentTypeSelect.options)
            .filter((option) => option.dataset.dynamicDeleted === 'true')
            .forEach((option) => option.remove());
        documentTypeSelect.value = '';
        Array.from(riskBasedRequirementsSelect.options).forEach((option) => {
            option.selected = false;
        });
        updateSelectedFilesLabel();
        toggleExistingFilesVisibility(false);
    };

    const openEditModal = async (certificate) => {
        resetForm();
        state.editingCertificate = certificate;
        certificateIdInput.value = String(certificate.id);
        modalTitle.textContent = 'Modifica certificato';
        submitButton.textContent = 'Aggiorna certificato';
        form.elements.namedItem('name').value = certificate.name || '';
        form.elements.namedItem('description').value = certificate.description || '';
        ensureDocumentTypeOption(certificate);
        documentTypeSelect.value = certificate.document_type_id || '';
        form.elements.namedItem('issued_at').value = certificate.issued_at_iso || '';
        form.elements.namedItem('expires_at').value = certificate.expires_at_iso || '';
        form.elements.namedItem('internal_course_id').value = certificate.internal_course_id || '';

        const riskBasedRequirementIds = new Set((certificate.risk_based_requirements || []).map((riskBasedRequirement) => Number(riskBasedRequirement.id)));
        Array.from(riskBasedRequirementsSelect.options).forEach((option) => {
            option.selected = riskBasedRequirementIds.has(Number(option.value));
        });

        toggleExistingFilesVisibility(true);
        modal.showModal();
        await loadCertificateFiles();
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
            window.showFlash?.('success', 'Certificato eliminato con successo');
        } catch (error) {
            window.showFlash?.('error', error.response?.data?.message || 'Errore durante l\'eliminazione del certificato.');
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

    filesInput.addEventListener('change', () => {
        updateSelectedFilesLabel();
    });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
        filesDropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            event.stopPropagation();
            filesDropzone.classList.toggle('border-primary', eventName === 'dragenter' || eventName === 'dragover');
        });
    });

    filesDropzone.addEventListener('drop', (event) => {
        if (!event.dataTransfer?.files?.length) {
            return;
        }

        const transfer = new DataTransfer();

        Array.from(event.dataTransfer.files).forEach((file) => {
            transfer.items.add(file);
        });

        filesInput.files = transfer.files;
        filesInput.dispatchEvent(new Event('change', { bubbles: true }));
    });

    showDeletedFilesCheckbox.addEventListener('change', async () => {
        state.showDeletedFiles = showDeletedFilesCheckbox.checked;
        await loadCertificateFiles();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        submitButton.disabled = true;

        const formData = new FormData();
        formData.set('name', String(form.elements.namedItem('name').value || ''));
        formData.set('description', String(form.elements.namedItem('description').value || ''));
        formData.set('document_type_id', String(documentTypeSelect.value || ''));
        formData.set('issued_at', String(form.elements.namedItem('issued_at').value || ''));
        formData.set('expires_at', String(form.elements.namedItem('expires_at').value || ''));
        formData.set('internal_course_id', String(form.elements.namedItem('internal_course_id').value || ''));

        Array.from(riskBasedRequirementsSelect.selectedOptions).forEach((option) => {
            formData.append('risk_based_requirement_ids[]', option.value);
        });

        Array.from(filesInput.files || []).forEach((file) => {
            formData.append('files[]', file);
        });

        try {
            if (state.editingCertificate?.actions?.update_url) {
                formData.set('_method', 'PUT');
                await window.axios.post(state.editingCertificate.actions.update_url, formData, {
                    headers: { Accept: 'application/json' },
                });
            } else {
                await window.axios.post(storeUrl, formData, {
                    headers: { Accept: 'application/json' },
                });
            }

            modal.close();
            state.page = 1;
            await loadCertificates();
            await refreshRiskSummarySafely();
            window.showFlash?.('success', state.editingCertificate ? 'Certificato aggiornato con successo' : 'Certificato salvato con successo');
        } catch (error) {
            const message = error.response?.data?.message
                || Object.values(error.response?.data?.errors || {}).flat().join(' ')
                || 'Errore durante il salvataggio del certificato.';

            window.showFlash?.('error', message);
        } finally {
            submitButton.disabled = false;
        }
    });

    searchButton.addEventListener('click', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        void loadCertificates();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        state.search = searchInput.value.trim();
        state.page = 1;
        void loadCertificates();
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
            void loadCertificates();
        });
    });

    updateSelectedFilesLabel();
    void loadCertificates();
}
