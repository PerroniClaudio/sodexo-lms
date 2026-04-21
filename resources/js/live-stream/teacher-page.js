import * as TwilioVideo from 'twilio-video';
import {
    getRemoteVideoTrackByIdentity,
    isParticipantIdentityHighlighted,
    resolveParticipantSpeakingState,
} from './participant-utils.mjs';
import {
    createPlaceholderCard,
    createPreviewController,
    deterministicShuffle,
    getLiveStreamConfig,
    getLiveStreamIconButtonContent,
    getParticipantInitialsBadgeClassNames,
    getParticipantAudioStatusMarkup,
    getLiveStreamRoot,
    renderChatMessages,
    setBadgeState,
    setMessage,
} from './shared';
import {
    LIVE_STREAM_CAMERA_TRACK_NAME,
    LIVE_STREAM_SCREEN_TRACK_NAME,
} from './track-names.mjs';

const TEACHER_PIN_STORAGE_KEY = 'live-stream-teacher-pins';
const SPEECH_ACTIVATE_RMS_THRESHOLD = 0.05;
const SPEECH_RELEASE_RMS_THRESHOLD = 0.02;
const SPEECH_HOLD_MS = 300;

export function initTeacherPage() {
    const root = getLiveStreamRoot();

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const config = getLiveStreamConfig(root);

    if (!config) {
        return;
    }

    const state = {
        config,
        room: null,
        joined: false,
        latestState: null,
        audioNodes: new Map(),
        remoteAudioAnalysers: new Map(),
        speakingParticipantIdentities: new Set(),
        lastSpeakingIdentity: null,
        dominantSpeakerIdentity: null,
        remoteAudioContext: null,
        presenceHandle: null,
        screenShareTrack: null,
    };

    const startButton = root.querySelector('[data-live-stream-start-button]');
    const endButton = root.querySelector('[data-live-stream-end-button]');
    const micToggleButton = root.querySelector('[data-live-stream-teacher-local-mic-toggle]');
    const screenShareCard = root.querySelector('[data-live-stream-screen-share-card]');
    const screenShareButton = root.querySelector('[data-live-stream-screen-share-toggle]');
    const screenShareStatus = root.querySelector('[data-live-stream-screen-share-status]');
    const chatForm = root.querySelector('[data-live-stream-chat-form]');
    const chatInput = root.querySelector('[data-live-stream-chat-input]');
    const chatSubmitButton = root.querySelector('[data-live-stream-chat-submit]');
    const previewController = createPreviewController(root, {
        onAudioStateChange: syncMicToggleButton,
    });

    renderChatMessages(root, []);

    if (startButton instanceof HTMLButtonElement) {
        startButton.addEventListener('click', async () => {
            await startOrJoin();
        });
    }

    if (endButton instanceof HTMLButtonElement) {
        endButton.addEventListener('click', async () => {
            await endSession();
        });
    }

    if (micToggleButton instanceof HTMLButtonElement) {
        micToggleButton.addEventListener('click', () => {
            toggleLocalMic();
        });
    }

    if (screenShareButton instanceof HTMLButtonElement) {
        screenShareButton.addEventListener('click', () => {
            void toggleScreenShare();
        });
    }

    if (chatForm instanceof HTMLFormElement) {
        chatForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await sendChatMessage();
        });
    }

    syncMicToggleButton();
    syncScreenShareButton();

    window.addEventListener('beforeunload', () => {
        previewController.destroy();
        teardownRoom();
    });

    void fetchState();
    window.setInterval(() => {
        void fetchState();
    }, config.pollIntervals.state);

    async function fetchState() {
        try {
            const response = await window.axios.get(config.routes.state);
            state.latestState = response.data;
            syncSpeakingParticipants();

            updateTopBar();
            renderTeacherGrid();
            renderParticipantList();
            renderChatMessages(root, response.data.messages ?? []);

            if (response.data.status !== 'live' && state.room) {
                teardownRoom();
            }
        } catch (error) {
            console.error(error);
        }
    }

    function updateTopBar() {
        const payload = state.latestState;

        if (!payload) {
            return;
        }

        if (payload.status === 'live') {
            setBadgeState(root, 'Live', 'badge-success');
            setMessage(root, state.joined ? 'Diretta avviata e docente collegato.' : 'La diretta è attiva. Puoi rientrare.');
        } else {
            setBadgeState(root, 'Preflight', 'badge-outline');
            setMessage(root, 'Prepara l’anteprima, poi avvia la diretta.');
        }

        if (startButton instanceof HTMLButtonElement) {
            const shouldShowStartButton = payload.status !== 'live' || !state.joined;

            startButton.disabled = state.joined;
            startButton.textContent = payload.status === 'live' ? 'Rientra nella diretta' : 'Avvia diretta';
            startButton.classList.toggle('hidden', !shouldShowStartButton);
        }

        if (endButton instanceof HTMLButtonElement) {
            const shouldShowEndButton = payload.status === 'live' && state.joined;

            endButton.classList.toggle('hidden', !shouldShowEndButton);
            endButton.classList.toggle('disabled', !shouldShowEndButton);
        }

        syncMicToggleButton();
        syncScreenShareButton();
        updateChatComposerState();
    }

    function updateChatComposerState() {
        if (!(chatInput instanceof HTMLInputElement) || !(chatSubmitButton instanceof HTMLButtonElement)) {
            return;
        }

        const chatAvailable = Boolean(config.routes.chat) && state.latestState?.status === 'live' && state.joined;

        chatInput.disabled = !chatAvailable;
        chatSubmitButton.disabled = !chatAvailable;

        if (!chatAvailable) {
            chatInput.value = '';
        }
    }

    async function startOrJoin() {
        if (!(startButton instanceof HTMLButtonElement) || state.joined) {
            return;
        }

        startButton.disabled = true;
        startButton.textContent = 'Connessione...';

        try {
            if (!previewController.hasAudioTrack()) {
                await previewController.open();
            }

            const shouldStartMuted = previewController.hasAudioTrack() && !previewController.isAudioEnabled();

            if (state.latestState?.status !== 'live') {
                await window.axios.post(config.routes.startSession);
            }

            const joinResponse = await window.axios.post(config.routes.join);
            const joinPayload = joinResponse.data;

            ensureRemoteAudioContext();

            state.room = await TwilioVideo.connect(joinPayload.twilio_token, {
                name: joinPayload.twilio_room_name,
                audio: true,
                video: {
                    name: LIVE_STREAM_CAMERA_TRACK_NAME,
                },
                dominantSpeaker: true,
            });

            if (shouldStartMuted) {
                state.room.localParticipant.audioTracks.forEach((publication) => {
                    publication.track.disable();
                });
            }

            subscribeToRoom();
            state.joined = true;
            syncMicToggleButton();
            syncScreenShareButton();

            state.presenceHandle = window.setInterval(() => {
                void sendPresence();
            }, config.pollIntervals.presence);

            await sendPresence();
            await fetchState();
        } catch (error) {
            startButton.disabled = false;
            startButton.textContent = 'Avvia diretta';
            console.error(error);
        }
    }

    async function endSession() {
        if (!(endButton instanceof HTMLButtonElement)) {
            return;
        }

        endButton.disabled = true;

        try {
            await window.axios.post(config.routes.endSession);
            teardownRoom();
            await fetchState();
        } catch (error) {
            console.error(error);
        } finally {
            endButton.disabled = false;
        }
    }

    async function sendChatMessage() {
        if (!(chatInput instanceof HTMLInputElement) || !config.routes.chat) {
            return;
        }

        const body = chatInput.value.trim();

        if (!body) {
            return;
        }

        chatInput.disabled = true;

        if (chatSubmitButton instanceof HTMLButtonElement) {
            chatSubmitButton.disabled = true;
        }

        try {
            await window.axios.post(config.routes.chat, { body });
            chatInput.value = '';
            await fetchState();
        } catch (error) {
            console.error(error);
        } finally {
            updateChatComposerState();
        }
    }

    function subscribeToRoom() {
        if (!state.room) {
            return;
        }

        state.room.participants.forEach((participant) => {
            subscribeToParticipant(participant);
        });

        state.room.on('participantConnected', (participant) => {
            subscribeToParticipant(participant);
            renderTeacherGrid();
        });

        state.room.on('participantDisconnected', (participant) => {
            detachParticipantAudio(participant);
            renderTeacherGrid();
        });

        state.room.on('dominantSpeakerChanged', (participant) => {
            state.dominantSpeakerIdentity = participant?.identity ?? null;

            if (state.dominantSpeakerIdentity) {
                state.lastSpeakingIdentity = state.dominantSpeakerIdentity;
            }

            renderTeacherGrid();
            renderParticipantList();
        });

        state.room.on('disconnected', () => {
            teardownRoom();
        });
    }

    function subscribeToParticipant(participant) {
        participant.tracks.forEach((publication) => {
            if (publication.isSubscribed) {
                handleTrackSubscribed(publication.track, participant);
            }

            publication.on('subscribed', (track) => handleTrackSubscribed(track, participant));
            publication.on('unsubscribed', (track) => handleTrackUnsubscribed(track));
        });
    }

    function handleTrackSubscribed(track, participant) {
        if (track.kind === 'audio') {
            attachAudioTrack(track, participant);
            return;
        }

        renderTeacherGrid();
    }

    function handleTrackUnsubscribed(track) {
        if (track.kind === 'audio') {
            detachAudioTrack(track.sid);
            return;
        }

        renderTeacherGrid();
    }

    function attachAudioTrack(track, participant) {
        const audioStage = root.querySelector('[data-live-stream-audio-stage]');

        if (!(audioStage instanceof HTMLElement) || state.audioNodes.has(track.sid)) {
            return;
        }

        const audioElement = track.attach();
        audioElement.dataset.identity = participant.identity;
        state.audioNodes.set(track.sid, audioElement);
        audioStage.appendChild(audioElement);
        startAudioAnalysis(track.sid, audioElement, participant.identity);
    }

    function detachParticipantAudio(participant) {
        participant.audioTracks.forEach((publication) => {
            if (publication.track) {
                detachAudioTrack(publication.track.sid);
            }
        });
    }

    function detachAudioTrack(trackSid) {
        stopAudioAnalysis(trackSid);

        const audioElement = state.audioNodes.get(trackSid);

        if (audioElement instanceof HTMLElement) {
            audioElement.remove();
        }

        state.audioNodes.delete(trackSid);
    }

    function getRemoteVideoTrack(identity) {
        return getRemoteVideoTrackByIdentity(state.room, identity);
    }

    function ensureRemoteAudioContext() {
        if (state.remoteAudioContext) {
            if (state.remoteAudioContext.state === 'suspended') {
                void state.remoteAudioContext.resume().catch(() => {});
            }

            return state.remoteAudioContext;
        }

        const AudioContextClass = window.AudioContext || window.webkitAudioContext;

        if (!AudioContextClass) {
            return null;
        }

        state.remoteAudioContext = new AudioContextClass();

        if (state.remoteAudioContext.state === 'suspended') {
            void state.remoteAudioContext.resume().catch(() => {});
        }

        return state.remoteAudioContext;
    }

    function syncSpeakingParticipants() {
        const audibleParticipants = new Set(
            (state.latestState?.participants ?? [])
                .filter((participant) => participant.audio_enabled)
                .map((participant) => participant.twilio_identity),
        );

        state.speakingParticipantIdentities.forEach((identity) => {
            if (!audibleParticipants.has(identity)) {
                state.speakingParticipantIdentities.delete(identity);
            }
        });

        if (state.lastSpeakingIdentity && !audibleParticipants.has(state.lastSpeakingIdentity)) {
            state.lastSpeakingIdentity = null;
        }

        if (state.dominantSpeakerIdentity && !audibleParticipants.has(state.dominantSpeakerIdentity)) {
            state.dominantSpeakerIdentity = null;
        }
    }

    function setParticipantSpeaking(identity, isSpeaking) {
        if (!identity) {
            return;
        }

        const hadIdentity = state.speakingParticipantIdentities.has(identity);

        if (isSpeaking) {
            state.speakingParticipantIdentities.add(identity);
            state.lastSpeakingIdentity = identity;
        } else {
            state.speakingParticipantIdentities.delete(identity);

            if (state.dominantSpeakerIdentity === identity) {
                state.dominantSpeakerIdentity = null;
            }

            if (state.lastSpeakingIdentity === identity) {
                const remainingSpeakers = [...state.speakingParticipantIdentities];
                state.lastSpeakingIdentity = remainingSpeakers.length > 0
                    ? remainingSpeakers[remainingSpeakers.length - 1]
                    : null;
            }
        }

        if (hadIdentity !== isSpeaking) {
            renderTeacherGrid();
            renderParticipantList();
        }
    }

    function isParticipantSpeaking(identity) {
        return isParticipantIdentityHighlighted(
            identity,
            state.speakingParticipantIdentities,
            state.dominantSpeakerIdentity,
        );
    }

    function startAudioAnalysis(trackSid, audioElement, participantIdentity) {
        if (state.remoteAudioAnalysers.has(trackSid)) {
            return;
        }

        const audioContext = ensureRemoteAudioContext();

        if (!audioContext) {
            return;
        }

        const analyserNode = audioContext.createAnalyser();
        analyserNode.fftSize = 256;

        const sourceNode = audioContext.createMediaElementSource(audioElement);
        sourceNode.connect(analyserNode);
        analyserNode.connect(audioContext.destination);

        const monitor = {
            analyserNode,
            sourceNode,
            animationFrameId: null,
            identity: participantIdentity,
            isSpeaking: false,
        };
        const dataArray = new Uint8Array(analyserNode.frequencyBinCount);
        let lastHeardAt = 0;

        const analyse = () => {
            analyserNode.getByteTimeDomainData(dataArray);

            let sum = 0;

            dataArray.forEach((value) => {
                const normalized = (value - 128) / 128;
                sum += normalized * normalized;
            });

            const rms = Math.sqrt(sum / dataArray.length);
            const nextState = resolveParticipantSpeakingState({
                rms,
                isSpeaking: monitor.isSpeaking,
                lastHeardAt,
                now: Date.now(),
                activateThreshold: SPEECH_ACTIVATE_RMS_THRESHOLD,
                releaseThreshold: SPEECH_RELEASE_RMS_THRESHOLD,
                holdMs: SPEECH_HOLD_MS,
            });

            lastHeardAt = nextState.lastHeardAt;

            if (monitor.isSpeaking !== nextState.isSpeaking) {
                monitor.isSpeaking = nextState.isSpeaking;
                setParticipantSpeaking(participantIdentity, nextState.isSpeaking);
            }

            monitor.animationFrameId = window.requestAnimationFrame(analyse);
        };

        monitor.animationFrameId = window.requestAnimationFrame(analyse);

        state.remoteAudioAnalysers.set(trackSid, monitor);
    }

    function stopAudioAnalysis(trackSid) {
        const analysis = state.remoteAudioAnalysers.get(trackSid);

        if (!analysis) {
            return;
        }

        if (analysis.animationFrameId !== null) {
            window.cancelAnimationFrame(analysis.animationFrameId);
        }

        analysis.sourceNode.disconnect();
        analysis.analyserNode.disconnect();
        setParticipantSpeaking(analysis.identity, false);
        state.remoteAudioAnalysers.delete(trackSid);
    }

    function renderTeacherGrid() {
        const grid = root.querySelector('[data-live-stream-teacher-grid]');
        const participants = state.latestState?.participants ?? [];

        if (!(grid instanceof HTMLElement)) {
            return;
        }

        grid.replaceChildren();

        if (participants.length === 0) {
            grid.appendChild(
                createPlaceholderCard('In attesa di partecipanti', '', {
                    className: 'min-h-[16rem] md:col-span-2 xl:col-span-3',
                    centered: true,
                    footer: 'Le webcam degli studenti compariranno qui',
                    hideInitials: true,
                }),
            );

            return;
        }

        const selection = selectTeacherParticipants(participants);

        selection.forEach((participant) => {
            const highlighted = isParticipantSpeaking(participant.twilio_identity);
            const track = getRemoteVideoTrack(participant.twilio_identity);

            if (!track) {
                grid.appendChild(
                    createPlaceholderCard(participant.name, 'Discente', {
                        initials: participant.initials,
                        highlighted,
                        className: 'min-h-[16rem]',
                    }),
                );

                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'overflow-hidden rounded-box border border-base-300 bg-neutral';

            if (highlighted) {
                wrapper.classList.add('border-success', 'ring-2', 'ring-success/50');
            }

            const videoElement = document.createElement('video');
            videoElement.className = 'aspect-video w-full bg-neutral object-cover';
            videoElement.autoplay = true;
            videoElement.playsInline = true;
            track.attach(videoElement);

            wrapper.appendChild(videoElement);
            wrapper.insertAdjacentHTML(
                'beforeend',
                `<div class="border-t border-base-300 bg-base-100 px-3 py-2 text-sm font-medium text-base-content">${participant.name}</div>`,
            );

            grid.appendChild(wrapper);
        });
    }

    function renderParticipantList() {
        const list = root.querySelector('[data-live-stream-participant-list]');
        const count = root.querySelector('[data-live-stream-participant-count]');
        const participants = state.latestState?.participants ?? [];
        const pendingHandRaiseIds = new Set((state.latestState?.pending_hand_raises ?? []).map((item) => item.user_id));

        if (!(list instanceof HTMLElement)) {
            return;
        }

        list.replaceChildren();

        if (count instanceof HTMLElement) {
            count.textContent = `${participants.length}`;
        }

        participants.forEach((participant) => {
            const isPinned = getPinnedUserIds().includes(participant.user_id);
            const isSpeaking = isParticipantSpeaking(participant.twilio_identity);
            const item = document.createElement('div');
            item.className = 'rounded-box border border-base-300 bg-base-100 p-3';

            item.innerHTML = `
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="${getParticipantInitialsBadgeClassNames(isSpeaking)}">
                            ${participant.initials}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">${participant.name}</p>
                            <p class="text-xs text-base-content/60">
                                ${pendingHandRaiseIds.has(participant.user_id) ? 'Mano alzata' : getParticipantAudioStatusMarkup(participant.audio_enabled, 'h-3.5 w-3.5')}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-square btn-xs ${isPinned ? 'btn-secondary' : 'btn-ghost'}" data-action="pin" aria-label="${isPinned ? 'Sgancia' : 'Fissa'}" title="${isPinned ? 'Sgancia' : 'Fissa'}">
                            ${getLiveStreamIconButtonContent('pin', isPinned ? 'Sgancia' : 'Fissa')}
                        </button>
                        <button type="button" class="btn btn-square btn-xs ${participant.audio_enabled ? 'btn-warning' : 'btn-primary'}" data-action="speaker" aria-label="${participant.audio_enabled ? 'Muta' : 'Smuta'}" title="${participant.audio_enabled ? 'Muta' : 'Smuta'}">
                            ${getLiveStreamIconButtonContent(participant.audio_enabled ? 'mic' : 'mic-off', participant.audio_enabled ? 'Muta' : 'Smuta')}
                        </button>
                    </div>
                </div>
            `;

            item.querySelector('[data-action="pin"]')?.addEventListener('click', () => {
                togglePinnedUser(participant.user_id);
                renderTeacherGrid();
                renderParticipantList();
            });

            item.querySelector('[data-action="speaker"]')?.addEventListener('click', async () => {
                await window.axios.patch(config.routes.speakerTemplate.replace('__PARTICIPANT__', String(participant.id)), {
                    enabled: !participant.audio_enabled,
                });

                await fetchState();
            });

            list.appendChild(item);
        });
    }

    function selectTeacherParticipants(participants) {
        const pinnedIds = getPinnedUserIds();
        const seed = Math.floor(Date.now() / 60000);
        const shuffled = deterministicShuffle(participants, seed);
        const selected = [];
        const selectedIds = new Set();
        const activeSpeaker = participants.find((item) => item.twilio_identity === state.lastSpeakingIdentity && isParticipantSpeaking(item.twilio_identity))
            ?? participants.find((item) => isParticipantSpeaking(item.twilio_identity));

        pinnedIds.forEach((userId) => {
            const participant = participants.find((item) => item.user_id === userId);

            if (participant && !selectedIds.has(userId) && selected.length < 9) {
                selected.push(participant);
                selectedIds.add(userId);
            }
        });

        if (activeSpeaker && !selectedIds.has(activeSpeaker.user_id) && selected.length < 9) {
            selected.push(activeSpeaker);
            selectedIds.add(activeSpeaker.user_id);
        }

        shuffled.forEach((participant) => {
            if (!selectedIds.has(participant.user_id) && selected.length < 9) {
                selected.push(participant);
                selectedIds.add(participant.user_id);
            }
        });

        return selected;
    }

    function getPinnedUserIds() {
        try {
            const rawValue = window.localStorage.getItem(`${TEACHER_PIN_STORAGE_KEY}:${config.moduleId}`);
            const parsedValue = rawValue ? JSON.parse(rawValue) : [];

            return Array.isArray(parsedValue) ? parsedValue.map(Number).filter(Number.isFinite).slice(0, 9) : [];
        } catch (error) {
            console.error(error);

            return [];
        }
    }

    function togglePinnedUser(userId) {
        const pinnedIds = getPinnedUserIds();
        const nextIds = pinnedIds.includes(userId)
            ? pinnedIds.filter((value) => value !== userId)
            : [...pinnedIds, userId].slice(0, 9);

        window.localStorage.setItem(`${TEACHER_PIN_STORAGE_KEY}:${config.moduleId}`, JSON.stringify(nextIds));
    }

    function toggleLocalMic() {
        if (!(micToggleButton instanceof HTMLButtonElement)) {
            return;
        }

        if (!state.room && !previewController.hasAudioTrack()) {
            void previewController.open();
            return;
        }

        const localAudioTrack = state.room ? [...state.room.localParticipant.audioTracks.values()][0]?.track ?? null : null;

        if (localAudioTrack) {
            if (localAudioTrack.isEnabled) {
                localAudioTrack.disable();
            } else {
                localAudioTrack.enable();
            }

            syncMicToggleButton();
            void sendPresence();
            return;
        }

        if (previewController.hasAudioTrack()) {
            previewController.toggleAudio();
            syncMicToggleButton();
        }
    }

    async function toggleScreenShare() {
        if (state.screenShareTrack) {
            stopScreenShare();
            return;
        }

        await startScreenShare();
    }

    async function startScreenShare() {
        if (!(screenShareButton instanceof HTMLButtonElement) || !state.room || state.screenShareTrack) {
            return;
        }

        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getDisplayMedia !== 'function') {
            setScreenShareStatus('La condivisione schermo non è supportata da questo browser.');
            syncScreenShareButton();
            return;
        }

        screenShareButton.disabled = true;
        setScreenShareStatus('Seleziona lo schermo o la finestra da condividere.');

        let displayTrack = null;
        let localScreenTrack = null;

        try {
            const displayStream = await navigator.mediaDevices.getDisplayMedia({
                video: true,
                audio: false,
            });

            displayTrack = displayStream.getVideoTracks()[0] ?? null;

            if (!displayTrack) {
                throw new Error('Screen share track not available');
            }

            localScreenTrack = new TwilioVideo.LocalVideoTrack(displayTrack, {
                name: LIVE_STREAM_SCREEN_TRACK_NAME,
            });

            displayTrack.addEventListener('ended', handleScreenShareEnded, { once: true });

            state.screenShareTrack = localScreenTrack;
            await state.room.localParticipant.publishTrack(localScreenTrack, {
                priority: 'high',
            });

            setScreenShareStatus('Schermo condiviso nella diretta.');
            syncScreenShareButton();
            await sendPresence();
        } catch (error) {
            if (displayTrack) {
                displayTrack.removeEventListener('ended', handleScreenShareEnded);
            }

            if (localScreenTrack) {
                localScreenTrack.stop();
            } else if (displayTrack) {
                displayTrack.stop();
            }

            if (error instanceof DOMException && error.name === 'NotAllowedError') {
                setScreenShareStatus('Condivisione schermo annullata o non autorizzata.');
            } else {
                setScreenShareStatus('Impossibile avviare la condivisione schermo.');
            }

            syncScreenShareButton();
            console.error(error);
        }
    }

    function handleScreenShareEnded() {
        stopScreenShare({
            endedByBrowser: true,
        });
    }

    function stopScreenShare(options = {}) {
        const screenShareTrack = state.screenShareTrack;

        if (!screenShareTrack) {
            if (options.endedByBrowser) {
                setScreenShareStatus('La condivisione schermo è terminata.');
                syncScreenShareButton();
            }

            return;
        }

        const mediaStreamTrack = screenShareTrack.mediaStreamTrack ?? null;

        if (mediaStreamTrack) {
            mediaStreamTrack.removeEventListener('ended', handleScreenShareEnded);
        }

        if (state.room) {
            state.room.localParticipant.unpublishTrack(screenShareTrack);
        }

        screenShareTrack.detach().forEach((element) => element.remove());
        screenShareTrack.stop();

        state.screenShareTrack = null;

        setScreenShareStatus(
            options.endedByBrowser
                ? 'La condivisione schermo è terminata.'
                : 'Puoi condividere lo schermo dopo l’ingresso nella diretta.',
        );
        syncScreenShareButton();

        if (state.room) {
            void sendPresence();
        }
    }

    function syncMicToggleButton() {
        if (!(micToggleButton instanceof HTMLButtonElement)) {
            return;
        }

        const localAudioTrack = state.room ? [...state.room.localParticipant.audioTracks.values()][0]?.track ?? null : null;
        const hasAudioTrack = localAudioTrack !== null || previewController.hasAudioTrack();
        const isEnabled = localAudioTrack ? localAudioTrack.isEnabled : previewController.isAudioEnabled();

        micToggleButton.disabled = !hasAudioTrack;
        micToggleButton.classList.toggle('disabled', !hasAudioTrack);
        micToggleButton.setAttribute('aria-label', isEnabled ? 'Disattiva microfono' : 'Attiva microfono');
        micToggleButton.setAttribute('title', isEnabled ? 'Disattiva microfono' : 'Attiva microfono');
        micToggleButton.innerHTML = getLiveStreamIconButtonContent(isEnabled ? 'mic' : 'mic-off', isEnabled ? 'Disattiva microfono' : 'Attiva microfono');
    }

    function syncScreenShareButton() {
        if (!(screenShareButton instanceof HTMLButtonElement)) {
            return;
        }

        const canShareScreen = Boolean(state.joined && state.room);
        const isSharingScreen = state.screenShareTrack !== null;
        const buttonLabel = isSharingScreen ? 'Interrompi condivisione schermo' : 'Condividi schermo';

        if (screenShareCard instanceof HTMLElement) {
            screenShareCard.classList.toggle('hidden', !canShareScreen && !isSharingScreen);
        }

        screenShareButton.disabled = !canShareScreen;
        screenShareButton.classList.toggle('btn-outline', !isSharingScreen);
        screenShareButton.classList.toggle('btn-warning', isSharingScreen);
        screenShareButton.innerHTML = `${getLiveStreamIconButtonContent('screen-share', buttonLabel)}<span>${buttonLabel}</span>`;
    }

    function setScreenShareStatus(message) {
        if (screenShareStatus instanceof HTMLElement) {
            screenShareStatus.textContent = message;
        }
    }

    async function sendPresence() {
        if (!state.room) {
            return;
        }

        const localAudioTrack = [...state.room.localParticipant.audioTracks.values()][0]?.track ?? null;
        const localVideoTracks = [...state.room.localParticipant.videoTracks.values()]
            .map((publication) => publication.track)
            .filter(Boolean);

        try {
            await window.axios.post(config.routes.presence, {
                twilio_participant_sid: state.room.localParticipant.sid,
                audio_enabled: localAudioTrack ? localAudioTrack.isEnabled : false,
                video_enabled: localVideoTracks.some((track) => track.isEnabled),
            });
        } catch (error) {
            console.error(error);
        }
    }

    function teardownRoom() {
        if (state.presenceHandle) {
            window.clearInterval(state.presenceHandle);
            state.presenceHandle = null;
        }

        stopScreenShare();

        if (state.room) {
            const activeRoom = state.room;
            state.room = null;
            activeRoom.disconnect();
        }

        state.audioNodes.forEach((node) => node.remove());
        state.audioNodes.clear();
        state.remoteAudioAnalysers.forEach((_, trackSid) => {
            stopAudioAnalysis(trackSid);
        });
        state.speakingParticipantIdentities.clear();
        state.lastSpeakingIdentity = null;
        state.dominantSpeakerIdentity = null;

        if (state.remoteAudioContext) {
            void state.remoteAudioContext.close().catch(() => {});
            state.remoteAudioContext = null;
        }

        state.joined = false;

        if (startButton instanceof HTMLButtonElement) {
            startButton.disabled = false;
            startButton.textContent = 'Avvia diretta';
            startButton.classList.remove('hidden');
        }

        if (endButton instanceof HTMLButtonElement) {
            endButton.classList.add('hidden');
        }

        syncMicToggleButton();
        syncScreenShareButton();
    }
}
