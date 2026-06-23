/**
 * module-base.js
 * Funzioni e utilità comuni a tutti i tipi di modulo.
 */

/**
 * Ottiene il contenitore root del modulo con i data attributes
 * @returns {HTMLElement|null}
 */
export function getModuleRoot() {
    const wrapper = document.getElementById('module-player');
    const root = wrapper ? wrapper.closest('[data-module-id]') : null;
    
    if (!root) {
        console.warn('[module-player] Root element non trovato.');
        return null;
    }
    
    return root;
}

/**
 * Ottiene i dati del modulo dai data attributes
 * @param {HTMLElement} root 
 * @returns {Object}
 */
export function getModuleData(root) {
    return {
        courseId: root.dataset.courseId,
        moduleId: root.dataset.moduleId,
        moduleType: root.dataset.moduleType,
        moduleTitle: root.dataset.moduleTitle,
        passingScore: parseInt(root.dataset.passingScore ?? '0', 10),
        csrfToken: root.dataset.csrf,
        signedPlaybackUrl: root.dataset.signedPlaybackUrl,
        videoTrackingUrl: root.dataset.videoTrackingUrl,
        videoEventsUrl: root.dataset.videoEventsUrl,
        videoProgressUrl: root.dataset.videoProgressUrl,
        videoCompleteUrl: root.dataset.videoCompleteUrl,
        videoExercisesUrl: root.dataset.videoExercisesUrl,
        scormPackagesUrl: root.dataset.scormPackagesUrl,
        quizUrl: root.dataset.quizUrl,
        quizSubmitUrl: root.dataset.quizSubmitUrl,
        nextModuleUrl: root.dataset.nextModuleUrl,
        nextModuleTitle: root.dataset.nextModuleTitle,
        quizAccessGateActive: root.dataset.quizAccessGateActive === 'true',
        quizAccessGateRemainingSeconds: parseInt(root.dataset.quizAccessGateRemainingSeconds ?? '0', 10),
        quizAccessGateAvailableAt: root.dataset.quizAccessGateAvailableAt,
        quizAccessGatePreviousModuleTitle: root.dataset.quizAccessGatePreviousModuleTitle,
        quizAccessGateDelayMinutes: parseInt(root.dataset.quizAccessGateDelayMinutes ?? '0', 10),
    };
}

/**
 * Escaping HTML per prevenire XSS
 * @param {string} str 
 * @returns {string}
 */
export function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}

/**
 * Mostra un messaggio di errore
 * @param {string} message 
 * @param {HTMLElement} container 
 */
export function showError(message, container) {
    container.innerHTML = `
        <div class="alert alert-error">
            <span>${escapeHtml(message)}</span>
        </div>
    `;
}

/**
 * Mostra un messaggio di successo
 * @param {string} message 
 * @param {HTMLElement} container 
 */
export function showSuccess(message, container) {
    container.innerHTML = `
        <div class="alert alert-success">
            <span>${escapeHtml(message)}</span>
        </div>
    `;
}

/**
 * Fetch helper con gestione errori
 * @param {string} url 
 * @param {Object} options 
 * @returns {Promise}
 */
export async function fetchJSON(url, options = {}) {
    const defaultHeaders = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    };
    
    const response = await fetch(url, {
        ...options,
        headers: { ...defaultHeaders, ...options.headers },
    });
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    return response.json();
}

export async function refreshModulePlayerState() {
    const currentRoot = getModuleRoot();

    if (!currentRoot) {
        return;
    }

    const response = await fetch(window.location.href, {
        headers: {
            Accept: 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const html = await response.text();
    const parser = new DOMParser();
    const nextDocument = parser.parseFromString(html, 'text/html');
    const nextRoot = nextDocument.querySelector('[data-module-id]');

    if (!nextRoot) {
        throw new Error('Updated module root not found.');
    }

    [
        '[data-course-player-summary]',
        '[data-course-player-progress-card]',
        '[data-course-player-module-list]',
    ].forEach((selector) => {
        const currentElement = document.querySelector(selector);
        const nextElement = nextDocument.querySelector(selector);

        if (currentElement && nextElement) {
            currentElement.replaceWith(nextElement);
        }
    });

    currentRoot.dataset.nextModuleUrl = nextRoot.dataset.nextModuleUrl ?? '';
    currentRoot.dataset.nextModuleTitle = nextRoot.dataset.nextModuleTitle ?? '';
}
