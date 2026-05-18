import { getModuleData, getModuleRoot } from './module-base.js';

export function initScormModule() {
    const root = getModuleRoot();

    if (!root) {
        return;
    }

    const moduleData = getModuleData(root);
    const wrapper = document.getElementById('module-player');
    const template = document.getElementById('tpl-scorm');

    if (!wrapper || !template) {
        console.warn('[scorm] Template o contenitore non trovato');
        return;
    }

    wrapper.appendChild(template.content.cloneNode(true));

    const moduleRoot = wrapper.querySelector('[data-scorm-module-root]');

    if (!moduleRoot) {
        console.warn('[scorm] Root del modulo non trovato');
        return;
    }

    const ui = {
        loading: moduleRoot.querySelector('[data-scorm-loading]'),
        error: moduleRoot.querySelector('[data-scorm-error]'),
        empty: moduleRoot.querySelector('[data-scorm-empty]'),
        list: moduleRoot.querySelector('[data-scorm-list]'),
        refreshButton: moduleRoot.querySelector('[data-scorm-refresh]'),
        feedback: moduleRoot.querySelector('[data-scorm-feedback]'),
        feedbackText: moduleRoot.querySelector('[data-scorm-feedback-text]'),
        packageTemplate: moduleRoot.querySelector('[data-scorm-package-template]'),
    };

    if (!ui.loading || !ui.error || !ui.empty || !ui.list || !ui.refreshButton || !ui.feedback || !ui.feedbackText || !ui.packageTemplate) {
        console.warn('[scorm] Componenti UI mancanti');
        return;
    }

    const loadPackages = async (isRefresh = false) => {
        if (isRefresh) {
            showFeedback(ui, 'Aggiornamento dati in corso...');
        }

        ui.error.classList.add('hidden');

        if (!isRefresh) {
            ui.loading.classList.remove('hidden');
        }

        try {
            const response = await fetch(moduleData.scormPackagesUrl, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            renderPackages(ui, data.packages ?? []);

            if (isRefresh) {
                showFeedback(ui, 'Dettagli SCORM aggiornati.');
                window.setTimeout(() => {
                    hideFeedback(ui);
                }, 2500);
            }
        } catch (error) {
            console.error('[scorm] Errore caricamento pacchetti:', error);
            ui.error.classList.remove('hidden');

            if (isRefresh) {
                showFeedback(ui, 'Aggiornamento non riuscito. Riprova.');
            }
        } finally {
            ui.loading.classList.add('hidden');
        }
    };

    ui.refreshButton.addEventListener('click', () => {
        loadPackages(true);
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            loadPackages(true);
        }
    });

    loadPackages();
}

function renderPackages(ui, packages) {
    ui.list.replaceChildren();

    if (!packages.length) {
        ui.empty.classList.remove('hidden');
        ui.list.classList.add('hidden');
        return;
    }

    ui.empty.classList.add('hidden');

    packages.forEach((item) => {
        const fragment = ui.packageTemplate.content.cloneNode(true);
        const card = fragment.querySelector('[data-scorm-package-card]');

        if (!card) {
            return;
        }

        setText(card, '[data-scorm-package-title]', item.title ?? 'Pacchetto SCORM');
        setText(card, '[data-scorm-package-description]', item.description ?? 'Nessuna descrizione disponibile.');
        setText(card, '[data-scorm-package-version]', formatVersion(item.version));
        setText(card, '[data-scorm-package-status]', item.status_label ?? item.status ?? 'N/D');
        setText(card, '[data-scorm-package-learner-status]', item.learner_status ?? 'Non disponibile');
        setText(card, '[data-scorm-package-score]', item.score?.display ?? 'Non disponibile');
        setText(card, '[data-scorm-package-module-time]', item.module_time_spent ?? '00:00:00');
        setText(card, '[data-scorm-package-location]', item.lesson_location ?? 'Non disponibile');
        setText(card, '[data-scorm-package-session-status]', formatSessionStatus(item.session?.status));
        setText(
            card,
            '[data-scorm-package-last-activity]',
            item.session?.last_activity_label
                ? `Ultima attivita: ${item.session.last_activity_label}`
                : 'Nessuna attivita registrata'
        );
        setText(card, '[data-scorm-package-sco-count]', `${item.sco_count ?? 0} SCO rilevati`);
        setText(card, '[data-scorm-package-resource-count]', `${item.resource_count ?? 0} risorse nel manifest`);
        setText(card, '[data-scorm-package-identifier]', item.identifier ?? '-');
        setText(card, '[data-scorm-package-entry-point]', item.entry_point ?? '-');
        setText(card, '[data-scorm-package-sco-identifier]', item.sco_identifier ?? '-');
        setText(
            card,
            '[data-scorm-package-organization]',
            item.default_organization_title ?? item.default_organization ?? 'Non disponibile'
        );
        setText(card, '[data-scorm-package-resume]', formatResume(item.resume_entry, item.suspend_data_present));
        setText(
            card,
            '[data-scorm-package-tracked-time]',
            item.tracked_time ?? item.session?.recorded_session_label ?? 'Non disponibile'
        );

        toggleVisibility(card.querySelector('[data-scorm-package-completed]'), item.is_completed === true);

        const progressBlock = card.querySelector('[data-scorm-package-progress-block]');
        const progressEmpty = card.querySelector('[data-scorm-package-progress-empty]');
        const progressBar = card.querySelector('[data-scorm-package-progress-bar]');
        const progressValue = card.querySelector('[data-scorm-package-progress-value]');

        if (typeof item.progress_percent === 'number' && progressBlock && progressEmpty && progressBar && progressValue) {
            progressBar.value = item.progress_percent;
            progressValue.textContent = typeof item.max_progress_percent === 'number' && item.max_progress_percent > item.progress_percent
                ? `${item.progress_percent}% (max ${item.max_progress_percent}%)`
                : `${item.progress_percent}%`;
            progressBlock.classList.remove('hidden');
            progressEmpty.classList.add('hidden');
        }

        if (item.max_numeric_location && item.lesson_location) {
            setText(card, '[data-scorm-package-location]', `${item.lesson_location} (max ${item.max_numeric_location})`);
        }

        const errorBlock = card.querySelector('[data-scorm-package-error-block]');
        const errorText = card.querySelector('[data-scorm-package-error-text]');

        if (item.error_message && errorBlock && errorText) {
            errorText.textContent = item.error_message;
            errorBlock.classList.remove('hidden');
        }

        const playerLink = card.querySelector('[data-scorm-package-player-link]');

        if (item.launchable && item.player_url && playerLink instanceof HTMLAnchorElement) {
            playerLink.href = item.player_url;
            playerLink.classList.remove('hidden');
        }

        ui.list.appendChild(fragment);
    });

    ui.list.classList.remove('hidden');
}

function showFeedback(ui, message) {
    ui.feedbackText.textContent = message;
    ui.feedback.classList.remove('hidden');
}

function hideFeedback(ui) {
    ui.feedback.classList.add('hidden');
}

function setText(container, selector, value) {
    const element = container.querySelector(selector);

    if (element) {
        element.textContent = value;
    }
}

function toggleVisibility(element, shouldShow) {
    if (!element) {
        return;
    }

    if (shouldShow) {
        element.classList.remove('hidden');
        return;
    }

    element.classList.add('hidden');
}

function formatVersion(version) {
    return version ? `SCORM ${String(version).toUpperCase()}` : 'SCORM N/D';
}

function formatSessionStatus(status) {
    if (!status) {
        return 'Non avviata';
    }

    if (status === 'active') {
        return 'Attiva';
    }

    if (status === 'terminated') {
        return 'Terminata';
    }

    return status;
}

function formatResume(entry, hasSuspendData) {
    if (entry === 'resume' || hasSuspendData) {
        return 'Ripresa disponibile';
    }

    if (entry === 'ab-initio') {
        return 'Nuovo avvio';
    }

    return 'Non disponibile';
}
