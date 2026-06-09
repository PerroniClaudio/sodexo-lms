document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-admin-user-edit-page]');

    if (!page) {
        return;
    }

    initializeRiskSummary(page);
    initializeUserEditForm(page);
    initializeCertificatesTable(page);
});

function cloneTemplateElement(root, selector) {
    const template = root.querySelector(selector);

    if (!(template instanceof HTMLTemplateElement)) {
        return null;
    }

    const element = template.content.firstElementChild;

    if (!element) {
        return null;
    }

    return element.cloneNode(true);
}

function initializeRiskSummary(page) {
    const container = page.querySelector('[data-risk-summary]');

    if (!container) {
        return;
    }

    const summaryUrl = container.dataset.riskSummaryUrl;
    const messageElement = container.querySelector('[data-risk-summary-message]');
    const badgeElement = container.querySelector('[data-risk-summary-badge]');
    const riskBasedRequirementsContainer = container.querySelector('[data-risk-based-requirements-items]');
    const riskRequirementTemplate = page.querySelector('[data-risk-requirement-template]');
    const riskRequirementEmptyTemplate = page.querySelector('[data-risk-requirement-empty-template]');

    if (
        !summaryUrl
        || !messageElement
        || !badgeElement
        || !riskBasedRequirementsContainer
        || !(riskRequirementTemplate instanceof HTMLTemplateElement)
        || !(riskRequirementEmptyTemplate instanceof HTMLTemplateElement)
    ) {
        return;
    }

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
        riskBasedRequirementsContainer.replaceChildren();

        if (!Array.isArray(summary.risk_based_requirements) || summary.risk_based_requirements.length === 0) {
            const empty = cloneTemplateElement(page, '[data-risk-requirement-empty-template]');

            if (empty) {
                riskBasedRequirementsContainer.appendChild(empty);
            }

            return;
        }

        summary.risk_based_requirements.forEach((riskBasedRequirement) => {
            const requiredTypeLabel = riskBasedRequirement.required_course_validity_type_label
                ? String(riskBasedRequirement.required_course_validity_type_label).toLowerCase()
                : '';
            const item = cloneTemplateElement(page, '[data-risk-requirement-template]');

            if (!item) {
                return;
            }

            const nameElement = item.querySelector('[data-risk-requirement-name]');
            const descriptionElement = item.querySelector('[data-risk-requirement-description]');
            const statusElement = item.querySelector('[data-risk-requirement-status]');
            const coveringRiskElement = item.querySelector('[data-risk-requirement-covering-risk]');
            const requiredTypeElement = item.querySelector('[data-risk-requirement-required-type]');

            nameElement.textContent = riskBasedRequirement.risk_based_requirement_name || '';
            statusElement.classList.add(...requirementBadgeClass(riskBasedRequirement.status).split(' '));
            statusElement.textContent = riskBasedRequirement.status_label || '';

            if (riskBasedRequirement.risk_based_requirement_description) {
                descriptionElement.textContent = riskBasedRequirement.risk_based_requirement_description;
                descriptionElement.classList.remove('hidden');
            }

            if (riskBasedRequirement.covered_by_higher_risk_certificate && riskBasedRequirement.covering_risk_label) {
                coveringRiskElement.textContent = `Coperto da attestato valido di livello superiore: ${riskBasedRequirement.covering_risk_label}`;
                coveringRiskElement.classList.remove('hidden');
            }

            if (['missing', 'expired'].includes(riskBasedRequirement.status) && requiredTypeLabel !== '') {
                requiredTypeElement.textContent = `Richiesto: ${requiredTypeLabel}`;
                requiredTypeElement.classList.remove('hidden');
            }

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
    const certificateRowTemplate = page.querySelector('[data-certificate-row-template]');
    const certificateRiskRequirementBadgeTemplate = page.querySelector('[data-certificate-risk-requirement-badge-template]');
    const certificateRiskRequirementEmptyTemplate = page.querySelector('[data-certificate-risk-requirement-empty-template]');
    const certificateFileRowTemplate = page.querySelector('[data-certificate-file-row-template]');
    const certificatePaginationButtonTemplate = page.querySelector('[data-certificate-pagination-button-template]');

    if (!apiUrl || !storeUrl || !tableBody || !emptyState || !summary || !pagination || !searchInput || !searchButton || !loadingIndicator || !modal || !openModalButton || closeButtons.length === 0 || !form || !submitButton || !riskBasedRequirementsSelect || !certificateIdInput || !modalTitle || !documentTypeSelect || !filesSelection || !filesInput || !filesDropzone || !filesCreateHint || !existingFilesContainer || !filesSummary || !filesLoading || !filesTableBody || !filesEmptyState || !showDeletedFilesCheckbox || !(certificateRowTemplate instanceof HTMLTemplateElement) || !(certificateRiskRequirementBadgeTemplate instanceof HTMLTemplateElement) || !(certificateRiskRequirementEmptyTemplate instanceof HTMLTemplateElement) || !(certificateFileRowTemplate instanceof HTMLTemplateElement) || !(certificatePaginationButtonTemplate instanceof HTMLTemplateElement)) {
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
        existingFilesContainer.classList.toggle('flex', visible);
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

    const renderRiskBasedRequirements = (target, riskBasedRequirements) => {
        target.replaceChildren();

        if (!Array.isArray(riskBasedRequirements) || riskBasedRequirements.length === 0) {
            const empty = cloneTemplateElement(page, '[data-certificate-risk-requirement-empty-template]');

            if (empty) {
                target.appendChild(empty);
            }

            return;
        }

        riskBasedRequirements.forEach((riskBasedRequirement) => {
            const badge = cloneTemplateElement(page, '[data-certificate-risk-requirement-badge-template]');

            if (!badge) {
                return;
            }

            badge.textContent = riskBasedRequirement.name || '';
            target.appendChild(badge);
        });
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

    const renderLatestFile = (row, tableRow) => {
        const emptyElement = tableRow.querySelector('[data-certificate-latest-file-empty]');
        const latestFileElement = tableRow.querySelector('[data-certificate-latest-file]');
        const latestFileNameElement = tableRow.querySelector('[data-certificate-latest-file-name]');
        const latestFileSummaryElement = tableRow.querySelector('[data-certificate-latest-file-summary]');

        if (!row.latest_active_file) {
            emptyElement.classList.remove('hidden');
            latestFileElement.classList.add('hidden');

            return;
        }

        const file = row.latest_active_file;
        emptyElement.classList.add('hidden');
        latestFileElement.classList.remove('hidden');
        latestFileNameElement.textContent = file.original_name || '';
        latestFileSummaryElement.textContent = formatFilesSummary(row.active_files_count || 0, row.total_files_count || 0);
    };

    const renderRows = (rows) => {
        tableBody.replaceChildren();

        rows.forEach((row) => {
            const tableRow = cloneTemplateElement(page, '[data-certificate-row-template]');

            if (!tableRow) {
                return;
            }

            const nameElement = tableRow.querySelector('[data-certificate-name]');
            const issuedAtElement = tableRow.querySelector('[data-certificate-issued-at]');
            const expiresAtElement = tableRow.querySelector('[data-certificate-expires-at]');
            const typeBadgeElement = tableRow.querySelector('[data-certificate-type-badge]');
            const documentTypeEmptyElement = tableRow.querySelector('[data-certificate-document-type-empty]');
            const documentTypeBadgeElement = tableRow.querySelector('[data-certificate-document-type-badge]');
            const riskRequirementsElement = tableRow.querySelector('[data-certificate-risk-requirements]');
            const previewDisabledElement = tableRow.querySelector('[data-certificate-preview-disabled]');
            const downloadDisabledElement = tableRow.querySelector('[data-certificate-download-disabled]');
            const previewLatestButton = tableRow.querySelector('[data-action="preview-latest"]');
            const downloadLatestButton = tableRow.querySelector('[data-action="download-latest"]');

            nameElement.textContent = row.name || '';
            issuedAtElement.textContent = row.issued_at || '-';
            expiresAtElement.textContent = row.expires_at || '-';
            typeBadgeElement.classList.add(...(row.is_internal ? ['badge-success', 'badge-soft'] : ['badge-neutral', 'badge-soft']));
            typeBadgeElement.textContent = row.type_label || '';

            if (row.document_type_name) {
                documentTypeBadgeElement.textContent = `${row.document_type_name}${row.document_type_is_deleted ? ' (eliminata)' : ''}`;
                documentTypeBadgeElement.classList.remove('hidden');
                documentTypeEmptyElement.classList.add('hidden');
            } else {
                documentTypeBadgeElement.classList.add('hidden');
                documentTypeEmptyElement.classList.remove('hidden');
            }

            renderLatestFile(row, tableRow);
            renderRiskBasedRequirements(riskRequirementsElement, row.risk_based_requirements);

            if (row.latest_active_file?.actions?.preview_url) {
                previewLatestButton.classList.remove('hidden');
                previewDisabledElement.classList.add('hidden');
                previewLatestButton.addEventListener('click', () => {
                    openPreview(row.latest_active_file.actions.preview_url);
                });
            } else {
                previewLatestButton.classList.add('hidden');
                previewDisabledElement.classList.remove('hidden');
            }

            if (row.latest_active_file?.actions?.download_url) {
                downloadLatestButton.classList.remove('hidden');
                downloadDisabledElement.classList.add('hidden');
                downloadLatestButton.addEventListener('click', () => {
                    downloadFile(row.latest_active_file.actions.download_url);
                });
            } else {
                downloadLatestButton.classList.add('hidden');
                downloadDisabledElement.classList.remove('hidden');
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
        filesTableBody.replaceChildren();
        filesSummary.textContent = formatFilesSummary(meta?.active_files_count ?? 0, meta?.total_files_count ?? 0);
        filesEmptyState.classList.toggle('hidden', files.length > 0);

        files.forEach((file) => {
            const rowElement = cloneTemplateElement(page, '[data-certificate-file-row-template]');

            if (!rowElement) {
                return;
            }

            const nameElement = rowElement.querySelector('[data-certificate-file-name]');
            const sizeElement = rowElement.querySelector('[data-certificate-file-size]');
            const uploadedAtElement = rowElement.querySelector('[data-certificate-file-uploaded-at]');
            const statusElement = rowElement.querySelector('[data-certificate-file-status]');
            const deletedAtElement = rowElement.querySelector('[data-certificate-file-deleted-at]');
            const deleteButton = rowElement.querySelector('[data-action="delete"]');

            nameElement.textContent = file.original_name || '';
            sizeElement.textContent = file.size_label || '';
            uploadedAtElement.textContent = file.uploaded_at || '-';
            statusElement.classList.add(file.is_deleted ? 'badge-warning' : 'badge-success');
            statusElement.textContent = file.is_deleted ? 'Eliminato' : 'Attivo';

            if (file.deleted_at) {
                deletedAtElement.textContent = `Soft delete: ${file.deleted_at}`;
                deletedAtElement.classList.remove('hidden');
            }

            rowElement.querySelector('[data-action="preview"]').addEventListener('click', () => {
                openPreview(file.actions.preview_url);
            });

            rowElement.querySelector('[data-action="download"]').addEventListener('click', () => {
                downloadFile(file.actions.download_url);
            });

            if (deleteButton) {
                if (file.is_deleted) {
                    deleteButton.classList.add('hidden');
                } else {
                    deleteButton.classList.remove('hidden');
                }

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

            filesTableBody.appendChild(rowElement);
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
        pagination.replaceChildren();

        if (!meta || meta.last_page <= 1) {
            return;
        }

        for (let pageNumber = 1; pageNumber <= meta.last_page; pageNumber += 1) {
            const button = cloneTemplateElement(page, '[data-certificate-pagination-button-template]');

            if (!button) {
                continue;
            }

            button.textContent = String(pageNumber);

            if (pageNumber === meta.current_page) {
                button.classList.add('btn-active');
            }

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
            tableBody.replaceChildren();
            emptyState.classList.remove('hidden');
            summary.textContent = 'Errore nel caricamento dei certificati.';
            pagination.replaceChildren();
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
            filesTableBody.replaceChildren();
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
        filesTableBody.replaceChildren();
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
