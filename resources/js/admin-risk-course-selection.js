/**
 * Gestione tabella corsi consigliati per selezione manuale rischio
 */

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('recommended-courses-table');
    
    if (!container) {
        return;
    }

    const apiUrl = container.dataset.apiUrl;
    const enrollUrl = container.dataset.enrollUrl;
    const csrfToken = container.dataset.csrfToken;
    
    const tableBody = container.querySelector('[data-courses-table-body]');
    const loadingElement = container.querySelector('[data-courses-loading]');
    const emptyElement = container.querySelector('[data-courses-empty]');
    const paginationInfo = container.querySelector('[data-pagination-info]');
    const paginationButtons = container.querySelector('[data-pagination-buttons]');
    const searchInput = document.querySelector('[data-courses-search-input]');
    const searchButton = document.querySelector('[data-courses-search-button]');
    const enrollModal = document.getElementById('enroll-confirmation-modal');
    const modalCourseTitle = document.querySelector('[data-modal-course-title]');
    const modalCourseId = document.querySelector('[data-modal-course-id]');
    const enrollForm = document.querySelector('[data-enroll-form]');

    let currentParams = {
        search: '',
        sort: 'title',
        direction: 'asc',
        page: 1,
    };

    /**
     * Carica i corsi dall'API
     */
    async function loadCourses() {
        if (loadingElement) {
            loadingElement.classList.remove('hidden');
        }
        if (tableBody) {
            tableBody.innerHTML = '';
        }
        if (emptyElement) {
            emptyElement.classList.add('hidden');
        }

        try {
            const params = new URLSearchParams(currentParams);
            const response = await fetch(`${apiUrl}?${params.toString()}`);
            const data = await response.json();

            if (loadingElement) {
                loadingElement.classList.add('hidden');
            }

            if (data.data.length === 0) {
                if (emptyElement) {
                    emptyElement.classList.remove('hidden');
                }
                if (paginationInfo) {
                    paginationInfo.textContent = '';
                }
                if (paginationButtons) {
                    paginationButtons.innerHTML = '';
                }
                return;
            }

            renderTable(data.data);
            renderPagination(data.meta);
            updateSortIcons();
        } catch (error) {
            console.error('Errore nel caricamento dei corsi:', error);
            if (loadingElement) {
                loadingElement.classList.add('hidden');
            }
            if (tableBody) {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-error">${'Errore nel caricamento dei corsi'}</td></tr>`;
            }
        }
    }

    /**
     * Renderizza la tabella con i dati dei corsi
     */
    function renderTable(courses) {
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = courses.map(course => {
            const requirementsBadges = course.covered_requirements
                .map(req => `<span class="badge badge-sm badge-outline">${escapeHtml(req.name)}</span>`)
                .join(' ');

            const enrollButton = course.eligible_to_enroll
                ? `<button 
                    type="button" 
                    class="btn btn-primary btn-sm" 
                    data-enroll-course-id="${course.course_id}"
                    data-enroll-course-title="${escapeHtml(course.title)}"
                >
                    ${'Assegna corso'}
                </button>`
                : `<button type="button" class="btn btn-disabled btn-sm" disabled title="${escapeHtml(course.ineligible_reason || '')}">
                    ${'Non idoneo'}
                </button>`;

            const warningMessage = !course.eligible_to_enroll && course.ineligible_reason
                ? `<div class="text-xs text-warning mt-1">${escapeHtml(course.ineligible_reason)}</div>`
                : '';

            return `
                <tr class="hover:bg-base-200">
                    <td>
                        <div class="font-semibold">${escapeHtml(course.title)}</div>
                        ${course.description ? `<div class="text-sm text-base-content/70 mt-1">${escapeHtml(course.description)}</div>` : ''}
                    </td>
                    <td>
                        <span class="badge badge-outline">${escapeHtml(course.course_type_label)}</span>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            ${requirementsBadges}
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-soft">${escapeHtml(course.validity_type_label)}</span>
                    </td>
                    <td>
                        <div class="text-sm">${escapeHtml(course.prerequisites_label)}</div>
                    </td>
                    <td>
                        ${enrollButton}
                        ${warningMessage}
                    </td>
                </tr>
            `;
        }).join('');

        // Aggiungi event listeners ai bottoni di iscrizione
        tableBody.querySelectorAll('[data-enroll-course-id]').forEach(button => {
            button.addEventListener('click', () => {
                const courseId = button.dataset.enrollCourseId;
                const courseTitle = button.dataset.enrollCourseTitle;
                openEnrollModal(courseId, courseTitle);
            });
        });
    }

    /**
     * Renderizza la paginazione
     */
    function renderPagination(meta) {
        if (paginationInfo) {
            if (meta.from && meta.to) {
                paginationInfo.textContent = `Visualizzazione ${meta.from}-${meta.to} di ${meta.total} corsi`;
            } else {
                paginationInfo.textContent = '';
            }
        }

        if (paginationButtons) {
            paginationButtons.innerHTML = '';

            if (meta.last_page <= 1) {
                return;
            }

            // Bottone pagina precedente
            const prevButton = document.createElement('button');
            prevButton.type = 'button';
            prevButton.className = `btn btn-sm ${meta.current_page === 1 ? 'btn-disabled' : ''}`;
            prevButton.textContent = '« Precedente';
            prevButton.disabled = meta.current_page === 1;
            prevButton.addEventListener('click', () => {
                if (meta.current_page > 1) {
                    currentParams.page = meta.current_page - 1;
                    loadCourses();
                }
            });
            paginationButtons.appendChild(prevButton);

            // Bottoni pagine
            const startPage = Math.max(1, meta.current_page - 2);
            const endPage = Math.min(meta.last_page, meta.current_page + 2);

            for (let i = startPage; i <= endPage; i++) {
                const pageButton = document.createElement('button');
                pageButton.type = 'button';
                pageButton.className = `btn btn-sm ${i === meta.current_page ? 'btn-active' : ''}`;
                pageButton.textContent = i;
                pageButton.addEventListener('click', () => {
                    currentParams.page = i;
                    loadCourses();
                });
                paginationButtons.appendChild(pageButton);
            }

            // Bottone pagina successiva
            const nextButton = document.createElement('button');
            nextButton.type = 'button';
            nextButton.className = `btn btn-sm ${meta.current_page === meta.last_page ? 'btn-disabled' : ''}`;
            nextButton.textContent = 'Successiva »';
            nextButton.disabled = meta.current_page === meta.last_page;
            nextButton.addEventListener('click', () => {
                if (meta.current_page < meta.last_page) {
                    currentParams.page = meta.current_page + 1;
                    loadCourses();
                }
            });
            paginationButtons.appendChild(nextButton);
        }
    }

    /**
     * Aggiorna le icone di ordinamento
     */
    function updateSortIcons() {
        // Reset tutte le icone
        container.querySelectorAll('[data-sort-icon]').forEach(icon => {
            icon.outerHTML = '<svg class="h-4 w-4 text-base-content/50" data-sort-icon="' + icon.dataset.sortIcon + '" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 16-4 4-4-4"/><path d="M17 20V4"/><path d="m3 8 4-4 4 4"/><path d="M7 4v16"/></svg>';
        });

        // Imposta l'icona corretta per la colonna ordinata
        const activeIcon = container.querySelector(`[data-sort-icon="${currentParams.sort}"]`);
        if (activeIcon) {
            const iconClass = currentParams.direction === 'asc' ? 'chevron-up' : 'chevron-down';
            const svgPath = currentParams.direction === 'asc'
                ? '<path d="m18 15-6-6-6 6"/>'
                : '<path d="m6 9 6 6 6-6"/>';
            
            activeIcon.outerHTML = `<svg class="h-4 w-4" data-sort-icon="${currentParams.sort}" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${svgPath}</svg>`;
        }
    }

    /**
     * Apri modal di conferma iscrizione
     */
    function openEnrollModal(courseId, courseTitle) {
        if (modalCourseTitle) {
            modalCourseTitle.textContent = `Corso: ${courseTitle}`;
        }
        if (modalCourseId) {
            modalCourseId.value = courseId;
        }
        if (enrollForm) {
            enrollForm.action = enrollUrl;
        }
        if (enrollModal) {
            enrollModal.showModal();
        }
    }

    /**
     * Gestione ricerca
     */
    if (searchButton) {
        searchButton.addEventListener('click', () => {
            currentParams.search = searchInput?.value || '';
            currentParams.page = 1;
            loadCourses();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                currentParams.search = searchInput.value || '';
                currentParams.page = 1;
                loadCourses();
            }
        });
    }

    /**
     * Gestione ordinamento
     */
    container.querySelectorAll('[data-sort-column]').forEach(sortLink => {
        sortLink.addEventListener('click', (e) => {
            e.preventDefault();
            const column = sortLink.dataset.sortColumn;
            
            if (currentParams.sort === column) {
                // Ciclo: asc -> desc -> nessun ordinamento
                if (currentParams.direction === 'asc') {
                    currentParams.direction = 'desc';
                } else {
                    // Reset a ordinamento di default
                    currentParams.sort = 'title';
                    currentParams.direction = 'asc';
                }
            } else {
                currentParams.sort = column;
                currentParams.direction = 'asc';
            }
            
            currentParams.page = 1;
            loadCourses();
        });
    });

    /**
     * Escape HTML per prevenire XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Carica i corsi iniziali
    loadCourses();
});
