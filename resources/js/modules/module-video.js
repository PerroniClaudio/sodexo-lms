/**
 * module-video.js
 * Gestisce la fruizione dei moduli video con Mux player.
 */

import { getModuleRoot, getModuleData, showError } from './module-base.js';

/**
 * Inizializza il modulo video
 */
export function initVideoModule() {
    const root = getModuleRoot();
    if (!root) return;

    const moduleData = getModuleData(root);
    const wrapper = document.getElementById('module-player');
    const tpl = document.getElementById('tpl-video');
    
    if (!tpl) {
        console.warn('[video] Template non trovato');
        return;
    }

    wrapper.appendChild(tpl.content.cloneNode(true));

    const loadingEl = wrapper.querySelector('#video-loading');
    const playerWrapper = wrapper.querySelector('#video-player-wrapper');
    const errorEl = wrapper.querySelector('#video-error');
    const completedMsg = wrapper.querySelector('#video-completed-msg');
    const playerContainer = wrapper.querySelector('[data-mux-player-container]');

    loadVideoPlayer(
        moduleData,
        loadingEl,
        playerWrapper,
        errorEl,
        completedMsg,
        playerContainer
    );
}

/**
 * Carica e configura il Mux player
 */
async function loadVideoPlayer(
    moduleData,
    loadingEl,
    playerWrapper,
    errorEl,
    completedMsg,
    playerContainer
) {
    try {
        const response = await fetch(moduleData.signedPlaybackUrl, {
            headers: { Accept: 'application/json' }
        });

        if (!response.ok) {
            throw new Error('Errore caricamento video');
        }

        const data = await response.json();
        
        createMuxPlayer(
            data,
            moduleData,
            playerContainer,
            loadingEl,
            playerWrapper,
            completedMsg
        );
    } catch (error) {
        console.error('[video] Errore:', error);
        loadingEl.classList.add('hidden');
        errorEl.classList.remove('hidden');
    }
}

/**
 * Crea e configura il Mux player
 */
function createMuxPlayer(
    videoData,
    moduleData,
    playerContainer,
    loadingEl,
    playerWrapper,
    completedMsg
) {
    const muxPlayer = document.createElement('mux-player');
    muxPlayer.setAttribute('stream-type', 'on-demand');
    muxPlayer.setAttribute(
        'src',
        `https://stream.mux.com/${videoData.playback_id}.m3u8?token=${videoData.token}`
    );
    muxPlayer.setAttribute('metadata-video-title', moduleData.moduleTitle ?? '');
    muxPlayer.setAttribute('primary-color', '#2563eb');
    muxPlayer.setAttribute('accent-color', '#2563eb');
    muxPlayer.setAttribute('style', 'width:100%;border-radius:8px;');

    // Riprendi dal punto in cui l'utente ha lasciato il video
    if (videoData.video_current_second && videoData.video_current_second > 0) {
        muxPlayer.setAttribute('start-time', String(videoData.video_current_second));
    }

    playerContainer.appendChild(muxPlayer);

    loadingEl.classList.add('hidden');
    playerWrapper.classList.remove('hidden');

    // Tracciamento del progresso
    setupProgressTracking(
        muxPlayer,
        videoData.video_current_second ?? 0,
        moduleData
    );

    // Gestione completamento
    muxPlayer.addEventListener('ended', () => {
        sendVideoComplete(moduleData, completedMsg);
    });
}

/**
 * Configura il tracciamento del progresso video
 */
function setupProgressTracking(muxPlayer, initialSecond, moduleData) {
    let lastProgressSecond = initialSecond;

    muxPlayer.addEventListener('timeupdate', () => {
        const current = Math.floor(muxPlayer.currentTime ?? 0);
        
        // Invia il progresso ogni 10 secondi
        if (current - lastProgressSecond >= 10) {
            lastProgressSecond = current;
            sendVideoProgress(current, moduleData);
        }
    });
}

/**
 * Invia il progresso video al server
 */
function sendVideoProgress(currentSecond, moduleData) {
    fetch(moduleData.videoProgressUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': moduleData.csrfToken,
        },
        body: JSON.stringify({ current_second: currentSecond }),
    }).catch((error) => {
        console.warn('[video] Errore salvataggio progresso:', error);
    });
}

/**
 * Invia la notifica di completamento al server
 */
async function sendVideoComplete(moduleData, completedMsgEl) {
    try {
        const response = await fetch(moduleData.videoCompleteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': moduleData.csrfToken,
            },
            body: JSON.stringify({}),
        });

        const data = await response.json();
        
        if (data.success && completedMsgEl) {
            completedMsgEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error('[video] Errore completamento:', error);
    }
}
