/**
 * module-video.js
 * Gestisce resume, anti-skip e completamento moduli video Mux.
 */

import { fetchJSON, getModuleData, getModuleRoot, showError } from './module-base.js';

const HEARTBEAT_INTERVAL_MS = 60_000;
const SEEK_GRACE_SECONDS = 3;

export function initVideoModule() {
    const root = getModuleRoot();

    if (!root) {
        return;
    }

    const moduleData = getModuleData(root);
    const wrapper = document.getElementById('module-player');
    const tpl = document.getElementById('tpl-video');

    if (!wrapper || !tpl) {
        return;
    }

    wrapper.appendChild(tpl.content.cloneNode(true));

    const loadingEl = wrapper.querySelector('#video-loading');
    const playerWrapper = wrapper.querySelector('#video-player-wrapper');
    const errorEl = wrapper.querySelector('#video-error');
    const completedMsg = wrapper.querySelector('#video-completed-msg');
    const playerContainer = wrapper.querySelector('[data-mux-player-container]');

    if (!loadingEl || !playerWrapper || !errorEl || !completedMsg || !playerContainer) {
        return;
    }

    loadVideoPlayer(moduleData, {
        loadingEl,
        playerWrapper,
        errorEl,
        completedMsg,
        playerContainer,
    });
}

async function loadVideoPlayer(moduleData, elements) {
    try {
        const [playbackData, trackingData] = await Promise.all([
            fetchJSON(moduleData.signedPlaybackUrl),
            fetchJSON(moduleData.videoTrackingUrl),
        ]);

        createMuxPlayer({
            playbackData,
            trackingData,
            moduleData,
            ...elements,
        });
    } catch (error) {
        console.error('[video] load failed', error);
        elements.loadingEl.classList.add('hidden');
        elements.errorEl.classList.remove('hidden');
        showError('Impossibile caricare il video. Riprova più tardi.', elements.errorEl);
    }
}

function createMuxPlayer({
    playbackData,
    trackingData,
    moduleData,
    loadingEl,
    playerWrapper,
    completedMsg,
    playerContainer,
}) {
    const initialState = {
        sessionUuid: crypto.randomUUID(),
        maxAllowedSecond: trackingData.max_allowed_second ?? playbackData.max_allowed_second ?? 0,
        lastHeartbeatSecond: trackingData.resume_second ?? playbackData.resume_second ?? 0,
        suppressSeekEvent: false,
        endedAcked: trackingData.is_completed ?? playbackData.is_completed ?? false,
        durationSeconds: trackingData.duration_seconds ?? playbackData.duration_seconds ?? null,
    };

    const muxPlayer = document.createElement('mux-player');
    muxPlayer.setAttribute('stream-type', 'on-demand');
    muxPlayer.setAttribute(
        'src',
        `https://stream.mux.com/${playbackData.playback_id}.m3u8?token=${playbackData.token}`
    );
    muxPlayer.setAttribute('metadata-video-title', moduleData.moduleTitle ?? '');
    muxPlayer.setAttribute('primary-color', '#2563eb');
    muxPlayer.setAttribute('accent-color', '#2563eb');
    muxPlayer.setAttribute('style', 'width:100%;border-radius:8px;');

    const resumeSecond = trackingData.resume_second ?? playbackData.resume_second ?? 0;

    if (resumeSecond > 0) {
        muxPlayer.setAttribute('start-time', String(resumeSecond));
    }

    playerContainer.appendChild(muxPlayer);

    loadingEl.classList.add('hidden');
    playerWrapper.classList.remove('hidden');

    setupProgressTracking(muxPlayer, initialState, moduleData, completedMsg);
}

function setupProgressTracking(muxPlayer, state, moduleData, completedMsg) {
    let heartbeatTimerId = null;
    let lastKnownSecond = state.lastHeartbeatSecond;
    let lastSeekFromSecond = state.lastHeartbeatSecond;

    const syncBlockedPlayback = (targetSecond) => {
        state.suppressSeekEvent = true;
        muxPlayer.currentTime = targetSecond;
        window.setTimeout(() => {
            state.suppressSeekEvent = false;
        }, 0);
    };

    const sendTrackingEvent = async (eventType, overrides = {}, options = {}) => {
        const currentSecond = Math.floor(muxPlayer.currentTime ?? 0);
        const payload = {
            session_uuid: state.sessionUuid,
            event_uuid: crypto.randomUUID(),
            event_type: eventType,
            occurred_at: new Date().toISOString(),
            position_second: currentSecond,
            max_second_client: state.maxAllowedSecond,
            delta_watched_seconds: 0,
            player_ended: false,
            client_payload: {
                event_source: options.keepalive ? 'keepalive' : 'interactive',
                duration_seconds: state.durationSeconds,
                seeking: muxPlayer.seeking,
            },
            ...overrides,
        };

        const response = await fetch(moduleData.videoEventsUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': moduleData.csrfToken,
            },
            body: JSON.stringify(payload),
            keepalive: options.keepalive === true,
        });

        if (!response.ok) {
            throw new Error(`Tracking request failed: ${response.status}`);
        }

        const data = await response.json();

        state.maxAllowedSecond = data.max_allowed_second ?? state.maxAllowedSecond;
        state.endedAcked = data.is_completed ?? state.endedAcked;

        if (typeof data.accepted_second === 'number') {
            lastKnownSecond = data.accepted_second;
        }

        if (data.was_blocked && typeof data.rewind_to_second === 'number') {
            syncBlockedPlayback(data.rewind_to_second);
        }

        return data;
    };

    const sendHeartbeat = async () => {
        const currentSecond = Math.floor(muxPlayer.currentTime ?? 0);
        const deltaWatchedSeconds = Math.max(0, currentSecond - lastKnownSecond);

        lastKnownSecond = currentSecond;
        state.lastHeartbeatSecond = currentSecond;

        await sendTrackingEvent('heartbeat', {
            position_second: currentSecond,
            max_second_client: state.maxAllowedSecond,
            delta_watched_seconds: deltaWatchedSeconds,
        });
    };

    const startHeartbeat = () => {
        stopHeartbeat();

        heartbeatTimerId = window.setInterval(() => {
            sendHeartbeat().catch((error) => {
                console.warn('[video] heartbeat failed', error);
            });
        }, HEARTBEAT_INTERVAL_MS);
    };

    const stopHeartbeat = () => {
        if (heartbeatTimerId !== null) {
            window.clearInterval(heartbeatTimerId);
            heartbeatTimerId = null;
        }
    };

    muxPlayer.addEventListener('play', () => {
        lastKnownSecond = Math.floor(muxPlayer.currentTime ?? 0);
        startHeartbeat();
        sendTrackingEvent('play').catch((error) => {
            console.warn('[video] play tracking failed', error);
        });
    });

    muxPlayer.addEventListener('pause', () => {
        stopHeartbeat();

        sendTrackingEvent('pause', {
            delta_watched_seconds: Math.max(0, Math.floor(muxPlayer.currentTime ?? 0) - lastKnownSecond),
        }).catch((error) => {
            console.warn('[video] pause tracking failed', error);
        });
    });

    muxPlayer.addEventListener('seeking', () => {
        if (state.suppressSeekEvent) {
            return;
        }

        lastSeekFromSecond = Math.floor(lastKnownSecond);
        const targetSecond = Math.floor(muxPlayer.currentTime ?? 0);

        if (targetSecond > (state.maxAllowedSecond + SEEK_GRACE_SECONDS)) {
            syncBlockedPlayback(state.maxAllowedSecond);
            return;
        }
    });

    muxPlayer.addEventListener('seeked', () => {
        if (state.suppressSeekEvent) {
            return;
        }

        const toSecond = Math.floor(muxPlayer.currentTime ?? 0);

        sendTrackingEvent('seek', {
            from_second: lastSeekFromSecond,
            to_second: toSecond,
            position_second: toSecond,
            delta_watched_seconds: 0,
        }).catch((error) => {
            console.warn('[video] seek tracking failed', error);
        });

        lastKnownSecond = toSecond;
    });

    muxPlayer.addEventListener('timeupdate', () => {
        const currentSecond = Math.floor(muxPlayer.currentTime ?? 0);

        if (!muxPlayer.seeking && currentSecond <= (state.maxAllowedSecond + SEEK_GRACE_SECONDS)) {
            state.maxAllowedSecond = Math.max(state.maxAllowedSecond, currentSecond);
        }
    });

    muxPlayer.addEventListener('ended', () => {
        stopHeartbeat();

        sendTrackingEvent('ended', {
            position_second: Math.floor(muxPlayer.currentTime ?? 0),
            delta_watched_seconds: Math.max(0, Math.floor(muxPlayer.currentTime ?? 0) - lastKnownSecond),
            player_ended: true,
        }).then((data) => {
            if (data.is_completed && completedMsg) {
                completedMsg.classList.remove('hidden');
                appendNextModuleButton(moduleData, completedMsg);
            }
        }).catch((error) => {
            console.error('[video] ended tracking failed', error);
        });
    });

    const flushBeforeUnload = () => {
        sendTrackingEvent('pause', {
            position_second: Math.floor(muxPlayer.currentTime ?? 0),
            delta_watched_seconds: Math.max(0, Math.floor(muxPlayer.currentTime ?? 0) - lastKnownSecond),
        }, {
            keepalive: true,
        }).catch(() => {});
    };

    window.addEventListener('pagehide', flushBeforeUnload);
    window.addEventListener('beforeunload', flushBeforeUnload);
}

function appendNextModuleButton(moduleData, completedMsgEl) {
    if (!moduleData.nextModuleUrl) {
        return;
    }

    const existingButton = completedMsgEl.parentElement?.querySelector('[data-next-module-button]');

    if (existingButton) {
        return;
    }

    const nextModuleBtn = document.createElement('div');
    nextModuleBtn.className = 'mt-4 flex justify-end';
    nextModuleBtn.dataset.nextModuleButton = 'true';
    nextModuleBtn.innerHTML = `
        <a href="${moduleData.nextModuleUrl}" class="btn btn-primary">
            ${moduleData.nextModuleTitle || 'Modulo successivo'}
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
    `;
    completedMsgEl.parentElement?.appendChild(nextModuleBtn);
}
