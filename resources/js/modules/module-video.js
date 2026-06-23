/**
 * module-video.js
 * Gestisce resume, anti-skip e completamento moduli video Mux.
 */

import { escapeHtml, fetchJSON, getModuleData, getModuleRoot, refreshModulePlayerState, showError } from './module-base.js';

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
    const playerContainer = wrapper.querySelector('[data-mux-player-container]');

    if (!loadingEl || !playerWrapper || !errorEl || !playerContainer) {
        return;
    }

    loadVideoPlayer(moduleData, {
        loadingEl,
        playerWrapper,
        errorEl,
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
    playerContainer,
}) {
    const initialState = {
        sessionUuid: crypto.randomUUID(),
        maxAllowedSecond: trackingData.max_allowed_second ?? playbackData.max_allowed_second ?? 0,
        lastHeartbeatSecond: trackingData.resume_second ?? playbackData.resume_second ?? 0,
        suppressSeekEvent: false,
        endedAcked: trackingData.is_completed ?? playbackData.is_completed ?? false,
        durationSeconds: trackingData.duration_seconds ?? playbackData.duration_seconds ?? null,
        exerciseController: null,
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

    setupProgressTracking(muxPlayer, initialState, moduleData);
}

function setupProgressTracking(muxPlayer, state, moduleData) {
    let heartbeatTimerId = null;
    let lastKnownSecond = state.lastHeartbeatSecond;
    let lastSeekFromSecond = state.lastHeartbeatSecond;
    let trackingQueue = Promise.resolve();

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

    const queueTrackingEvent = (eventType, overrides = {}, options = {}) => {
        trackingQueue = trackingQueue
            .catch(() => null)
            .then(() => sendTrackingEvent(eventType, overrides, options));

        return trackingQueue;
    };

    const sendHeartbeat = async () => {
        const currentSecond = Math.floor(muxPlayer.currentTime ?? 0);
        const deltaWatchedSeconds = Math.max(0, currentSecond - lastKnownSecond);

        lastKnownSecond = currentSecond;
        state.lastHeartbeatSecond = currentSecond;

        await queueTrackingEvent('heartbeat', {
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

    state.exerciseController = createVideoExerciseController(moduleData, muxPlayer);

    muxPlayer.addEventListener('play', () => {
        lastKnownSecond = Math.floor(muxPlayer.currentTime ?? 0);
        startHeartbeat();
        queueTrackingEvent('play').catch((error) => {
            console.warn('[video] play tracking failed', error);
        });
    });

    muxPlayer.addEventListener('pause', () => {
        stopHeartbeat();

        if (muxPlayer.ended || state.endedAcked) {
            return;
        }

        queueTrackingEvent('pause', {
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

        queueTrackingEvent('seek', {
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

        state.exerciseController?.openDueExercise(currentSecond);
    });

    muxPlayer.addEventListener('ended', () => {
        stopHeartbeat();
        state.endedAcked = true;

        queueTrackingEvent('ended', {
            position_second: Math.floor(muxPlayer.currentTime ?? 0),
            delta_watched_seconds: Math.max(0, Math.floor(muxPlayer.currentTime ?? 0) - lastKnownSecond),
            player_ended: true,
        }).then((data) => {
            if (data.is_completed) {
                window.showFlash?.('success', 'Modulo completato!');
                appendNextModuleButton(moduleData);
                refreshModulePlayerState().catch((error) => {
                    console.warn('[video] state refresh failed', error);
                });
            }
        }).catch((error) => {
            console.error('[video] ended tracking failed', error);
        });
    });

        const flushBeforeUnload = () => {
        if (muxPlayer.ended || state.endedAcked) {
            return;
        }

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

function createVideoExerciseController(moduleData, muxPlayer) {
    const modal = document.querySelector('[data-video-exercise-modal]');
    const titleEl = modal?.querySelector('[data-video-exercise-title]');
    const timerEl = modal?.querySelector('[data-video-exercise-timer]');
    const contentEl = modal?.querySelector('[data-video-exercise-content]');
    const submitBtn = modal?.querySelector('[data-video-exercise-submit]');
    const errorEl = modal?.querySelector('[data-video-exercise-error]');
    const downloadsModal = document.querySelector('[data-video-exercise-downloads-modal]');
    const downloadsContentEl = downloadsModal?.querySelector('[data-video-exercise-downloads-content]');
    const state = {
        exercises: [],
        activeExercise: null,
        elapsedSeconds: 0,
        timerId: null,
        autosaveId: null,
        openedIds: new Set(),
    };

    if (!modal || !titleEl || !timerEl || !contentEl || !submitBtn || !errorEl || !downloadsModal || !downloadsContentEl || !moduleData.videoExercisesUrl) {
        return null;
    }

    const load = async () => {
        try {
            const data = await fetchJSON(moduleData.videoExercisesUrl);
            state.exercises = data.exercises || [];
            const draft = state.exercises.find((exercise) => exercise.submission?.status === 'in_progress');

            if (draft) {
                openExercise(draft);
            }
        } catch (error) {
            console.warn('[video exercises] load failed', error);
        }
    };

    const openDueExercise = (currentSecond) => {
        const exercise = state.exercises.find((item) => {
            return item.submission?.status !== 'completed'
                && !state.openedIds.has(item.id)
                && currentSecond >= item.appears_at_seconds;
        });

        if (exercise) {
            openExercise(exercise);
        }
    };

    const openExercise = (exercise) => {
        state.activeExercise = exercise;
        state.openedIds.add(exercise.id);
        state.elapsedSeconds = Math.max(0, exercise.submission?.elapsed_seconds || 0);
        muxPlayer.pause();
        renderExercise(exercise);
        startTimers();
        modal.showModal();
    };

    const renderExercise = (exercise) => {
        titleEl.textContent = exercise.title;
        errorEl.classList.add('hidden');

        const downloadedMaterialIds = new Set(exercise.submission?.downloaded_material_ids || []);
        const requiredFileMaterials = exercise.materials.filter((material) => material.type === 'file');
        const allFilesDownloaded = requiredFileMaterials.every((material) => downloadedMaterialIds.has(material.id));
        const supportParts = [];

        if (exercise.materials.length > 0) {
            supportParts.push(`
                <div class="grid gap-2">
                    ${exercise.materials.map((material) => `
                        ${renderExerciseMaterial(material)}
                    `).join('')}
                </div>
            `);
        }

        contentEl.innerHTML = `
            <div class="collapse collapse-arrow border border-base-300 bg-base-100">
                <input type="checkbox" checked>
                <div class="collapse-title font-medium">Documentazione di supporto</div>
                <div class="collapse-content grid gap-3">
                    ${supportParts.join('') || '<p class="text-sm text-base-content/60">Nessuna documentazione di supporto.</p>'}
                </div>
            </div>
            <div class="collapse collapse-arrow border border-base-300 bg-base-100">
                <input type="checkbox" ${allFilesDownloaded ? 'checked' : ''}>
                <div class="collapse-title font-medium">Domande</div>
                <div class="collapse-content grid gap-4">
                    ${allFilesDownloaded
                        ? exercise.questions.map((question, index) => `
                            <fieldset class="fieldset gap-2" data-exercise-question="${question.id}" data-minimum-characters="${question.minimum_characters}">
                                <legend class="fieldset-legend text-sm font-medium">${escapeHtml(question.text)}</legend>
                                <textarea class="textarea textarea-bordered min-h-32 w-full resize-y" placeholder="Scrivi qui la tua risposta" data-answer-input="${question.id}">${escapeHtml(exercise.submission?.answers?.[question.id] || '')}</textarea>
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <span data-character-counter></span>
                                    <span class="text-base-content/60">Minimo ${question.minimum_characters} caratteri</span>
                                </div>
                            </fieldset>
                        `).join('')
                        : `<div class="alert">
                            <span>Scarica tutti i file della documentazione di supporto per visualizzare le domande.</span>
                        </div>`}
                </div>
            </div>
        `;

        contentEl.querySelectorAll('[data-exercise-material-download]').forEach((link) => {
            link.addEventListener('click', () => {
                const materialId = Number(link.dataset.exerciseMaterialDownload);

                if (!Number.isFinite(materialId)) {
                    return;
                }

                markMaterialAsDownloaded(exercise, materialId);
            });
        });

        contentEl.querySelectorAll('[data-answer-input]').forEach((input) => {
            input.addEventListener('input', updateSubmitState);
        });

        submitBtn.onclick = submitExercise;
        updateSubmitState();
    };

    const startTimers = () => {
        stopTimers();
        state.timerId = window.setInterval(() => {
            state.elapsedSeconds += 1;
            updateSubmitState();
        }, 1000);
        state.autosaveId = window.setInterval(() => {
            autosave().catch((error) => console.warn('[video exercises] autosave failed', error));
        }, HEARTBEAT_INTERVAL_MS);
    };

    const stopTimers = () => {
        if (state.timerId) {
            window.clearInterval(state.timerId);
        }

        if (state.autosaveId) {
            window.clearInterval(state.autosaveId);
        }

        state.timerId = null;
        state.autosaveId = null;
    };

    const answersPayload = () => {
        const answers = {};
        contentEl.querySelectorAll('[data-answer-input]').forEach((input) => {
            answers[input.dataset.answerInput] = input.value;
        });

        return answers;
    };

    const updateSubmitState = () => {
        const exercise = state.activeExercise;

        if (!exercise) {
            return;
        }

        const remainingSeconds = Math.max(0, exercise.minimum_seconds - state.elapsedSeconds);
        timerEl.textContent = remainingSeconds > 0
            ? `Tempo minimo rimanente: ${formatSeconds(remainingSeconds)}`
            : 'Tempo minimo completato';

        let answersAreValid = true;
        const downloadedMaterialIds = new Set(exercise.submission?.downloaded_material_ids || []);
        const requiredFileMaterials = exercise.materials.filter((material) => material.type === 'file');
        const filesAreReady = requiredFileMaterials.every((material) => downloadedMaterialIds.has(material.id));

        contentEl.querySelectorAll('[data-exercise-question]').forEach((questionEl) => {
            const input = questionEl.querySelector('[data-answer-input]');
            const counter = questionEl.querySelector('[data-character-counter]');
            const minimum = parseInt(questionEl.dataset.minimumCharacters || '1', 10);
            const length = input.value.trim().length;

            counter.textContent = `${length} / ${minimum}`;
            counter.className = length >= minimum ? 'text-success' : 'text-base-content/60';
            answersAreValid = answersAreValid && length >= minimum;
        });

        submitBtn.disabled = remainingSeconds > 0 || !answersAreValid || !filesAreReady;
    };

    const autosave = async () => {
        const exercise = state.activeExercise;

        if (!exercise || exercise.submission?.status === 'completed') {
            return null;
        }

        const data = await postJson(exercise.autosave_url, moduleData.csrfToken, {
            elapsed_seconds: state.elapsedSeconds,
            answers: answersPayload(),
            downloaded_material_ids: exercise.submission?.downloaded_material_ids || [],
        });

        exercise.submission = data.submission;

        return data;
    };

    const markMaterialAsDownloaded = (exercise, materialId) => {
        exercise.submission = exercise.submission || {
            status: 'in_progress',
            elapsed_seconds: state.elapsedSeconds,
            downloaded_material_ids: [],
            answers: {},
        };

        const downloadedMaterialIds = new Set(exercise.submission.downloaded_material_ids || []);

        if (downloadedMaterialIds.has(materialId)) {
            return;
        }

        downloadedMaterialIds.add(materialId);
        exercise.submission.downloaded_material_ids = Array.from(downloadedMaterialIds);
        exercise.materials = exercise.materials.map((material) => ({
            ...material,
            downloaded: material.id === materialId ? true : material.downloaded,
        }));

        renderExercise(exercise);
    };

    const submitExercise = async () => {
        const exercise = state.activeExercise;

        if (!exercise) {
            return;
        }

        errorEl.classList.add('hidden');

        try {
            const data = await postJson(exercise.submit_url, moduleData.csrfToken, {
                elapsed_seconds: state.elapsedSeconds,
                answers: answersPayload(),
                downloaded_material_ids: exercise.submission?.downloaded_material_ids || [],
            });

            exercise.submission = data.submission;
            stopTimers();
            modal.close();
            renderDownloadsModal(exercise, data);
            downloadsModal.showModal();
        } catch (error) {
            errorEl.textContent = 'Controlla tempo minimo e risposte prima di inviare.';
            errorEl.classList.remove('hidden');
        }
    };

    const renderDownloadsModal = (exercise, data) => {
        downloadsContentEl.innerHTML = `
            <div class="rounded border border-base-300 p-4">
                <p class="font-medium">${escapeHtml(exercise.title)}</p>
                <div class="mt-4 grid gap-2">
                    <a href="${escapeHtml(data.report_url)}" class="btn btn-primary justify-start">
                        <span>Scarica resoconto PDF</span>
                    </a>
                    ${data.self_evaluation_url
                        ? `<a href="${escapeHtml(data.self_evaluation_url)}" class="btn btn-outline justify-start">
                            <span>Scarica autovalutazione</span>
                        </a>`
                        : ''}
                </div>
            </div>
        `;
    };

    window.addEventListener('pagehide', () => {
        autosave().catch(() => {});
    });

    modal.addEventListener('cancel', (event) => {
        if (state.activeExercise?.submission?.status !== 'completed') {
            event.preventDefault();
        }
    });

    load();

    return { openDueExercise };
}

function renderExerciseMaterial(material) {
    if (material.type === 'file') {
        const isDownloaded = material.downloaded === true;

        return `
            <a href="${escapeHtml(material.url)}" class="btn btn-outline btn-sm justify-between" data-exercise-material-download="${material.id}">
                <span class="truncate">${escapeHtml(material.name)}</span>
                ${isDownloaded ? lucideCheckSvg('h-4 w-4 text-success') : '<span class="h-4 w-4"></span>'}
            </a>
        `;
    }

    if (material.type === 'video' && material.youtube_embed_url) {
        return `
            <div class="grid gap-2">
                <p class="font-medium">${escapeHtml(material.title)}</p>
                <div class="aspect-video overflow-hidden rounded border border-base-300">
                    <iframe src="${escapeHtml(material.youtube_embed_url)}" class="h-full w-full" allowfullscreen loading="lazy"></iframe>
                </div>
            </div>
        `;
    }

    return `
        <div class="rounded border border-base-300 p-3">
            <p class="mb-2 font-medium">${escapeHtml(material.title)}</p>
            <div class="prose max-w-none">${material.content_html || ''}</div>
        </div>
    `;
}

function lucideCheckSvg(className = 'h-4 w-4') {
    return `<svg xmlns="http://www.w3.org/2000/svg" class="${className}" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>`;
}

async function postJson(url, csrfToken, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }

    return response.json();
}

function formatSeconds(totalSeconds) {
    const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');

    return `${minutes}:${seconds}`;
}

function appendNextModuleButton(moduleData) {
    if (!moduleData.nextModuleUrl) {
        return;
    }

    const modulePlayer = document.getElementById('module-player');
    const playerCardBody = modulePlayer?.querySelector('.card .card-body');
    const existingButton = playerCardBody?.querySelector('[data-next-module-button]');

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
    playerCardBody?.appendChild(nextModuleBtn);
}
