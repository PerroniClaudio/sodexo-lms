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
    renderMuxStage,
    renderChatMessages,
    renderDocuments,
    setBadgeState,
    setMessage,
    shouldRetryLiveStreamConnectWithoutCamera,
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
        regiaModalOpen: false,
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
    const documentForm = root.querySelector('[data-live-stream-document-form]');
    const documentInput = root.querySelector('[data-live-stream-document-input]');
    const documentSubmitButton = root.querySelector('[data-live-stream-document-submit]');
    const documentFeedback = root.querySelector('[data-live-stream-document-feedback]');
    const pollToggleButton = root.querySelector('[data-live-stream-poll-toggle]');
    const pollForm = root.querySelector('[data-live-stream-poll-form]');
    const pollQuestionInput = root.querySelector('[data-live-stream-poll-question-input]');
    const pollOptionInputs = [...root.querySelectorAll('[data-live-stream-poll-option-input]')];
    const pollSubmitButton = root.querySelector('[data-live-stream-poll-submit]');
    const pollFeedback = root.querySelector('[data-live-stream-poll-feedback]');
    const deviceStatus = root.querySelector('[data-live-stream-device-status]');
    const muxStage = root.querySelector('[data-live-stream-mux-stage]');
    const regiaModal = root.querySelector('[data-live-stream-regia-modal]');
    const regiaModalCloseButton = root.querySelector('[data-live-stream-regia-modal-close]');
    const regiaStreamKey = root.querySelector('[data-live-stream-regia-stream-key]');
    const regiaIngestUrl = root.querySelector('[data-live-stream-regia-ingest-url]');
    const regiaPlaybackId = root.querySelector('[data-live-stream-regia-playback-id]');
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

    if (documentForm instanceof HTMLFormElement) {
        documentForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await uploadDocument();
        });
    }

    if (pollForm instanceof HTMLFormElement) {
        pollForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await publishPoll();
        });
    }

    if (pollToggleButton instanceof HTMLButtonElement) {
        pollToggleButton.addEventListener('click', () => {
            setPollComposerExpanded(pollForm instanceof HTMLFormElement ? pollForm.classList.contains('hidden') : false);
        });
    }

    if (regiaModal instanceof HTMLDialogElement && regiaModalCloseButton instanceof HTMLButtonElement) {
        regiaModalCloseButton.addEventListener('click', () => {
            closeRegiaModal();
        });
    }

    syncMicToggleButton();
    syncScreenShareButton();
    setPollComposerExpanded(false);

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
            renderMuxPlayer();
            renderParticipantList();
            renderChatMessages(root, response.data.messages ?? []);
            renderDocuments(root, response.data.documents ?? [], {
                canDeleteDocuments: Boolean(config.capabilities?.canManageDocuments),
                onDeleteDocument: deleteDocument,
            });
            renderPolls(response.data.polls ?? []);
            syncRegiaModal();

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
            const startLabel = config.capabilities?.canManageBroadcast
                ? (payload.status === 'live' ? 'Rientra in regia' : 'Avvia live')
                : (payload.status === 'live' ? 'Entra nella diretta' : 'In attesa della regia');

            startButton.disabled = state.joined || (!config.routes.startSession && payload.status !== 'live');
            startButton.textContent = startLabel;
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
        updatePollComposerState();
        renderMuxPlayer();
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

    function updatePollComposerState() {
        if (
            !(pollQuestionInput instanceof HTMLTextAreaElement) ||
            !(pollSubmitButton instanceof HTMLButtonElement) ||
            !(pollToggleButton instanceof HTMLButtonElement)
        ) {
            return;
        }

        const pollAvailable = Boolean(config.routes.pollsStore) && state.latestState?.status === 'live' && state.joined;

        pollToggleButton.disabled = !pollAvailable;
        pollQuestionInput.disabled = !pollAvailable;
        pollOptionInputs.forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.disabled = !pollAvailable;
            }
        });
        pollSubmitButton.disabled = !pollAvailable;

        if (!pollAvailable) {
            setPollFeedback('', false);
            setPollComposerExpanded(false);
        }
    }

    function setPollComposerExpanded(expanded) {
        if (!(pollForm instanceof HTMLFormElement) || !(pollToggleButton instanceof HTMLButtonElement)) {
            return;
        }

        pollForm.classList.toggle('hidden', !expanded);
        pollToggleButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        pollToggleButton.textContent = expanded ? 'Chiudi' : 'Crea sondaggio';

        if (!expanded) {
            setPollFeedback('', false);
        }
    }

    async function startOrJoin() {
        if (!(startButton instanceof HTMLButtonElement) || state.joined) {
            return;
        }

        startButton.disabled = true;
        startButton.textContent = 'Connessione...';

        try {
            if (config.capabilities?.requiresPreview && !previewController.hasAudioTrack()) {
                await previewController.open();
            }

            const shouldStartMuted = Boolean(config.capabilities?.requiresPreview) && previewController.hasAudioTrack() && !previewController.isAudioEnabled();

            if (state.latestState?.status !== 'live' && config.routes.startSession) {
                const startResponse = await window.axios.post(config.routes.startSession);
                state.latestState = {
                    ...(state.latestState ?? {}),
                    status: 'live',
                    mux: startResponse.data?.mux ?? state.latestState?.mux ?? config.mux,
                };
                syncRegiaModal(startResponse.data?.mux ?? null, {
                    playbackId: startResponse.data?.credentials?.playback_id,
                    streamKey: startResponse.data?.credentials?.stream_key,
                    ingestUrl: startResponse.data?.credentials?.ingest_url,
                });
            } else if (state.latestState?.status !== 'live' && !config.routes.startSession) {
                throw new Error('Live stream is not active yet.');
            }

            const joinResponse = await window.axios.post(config.routes.join);
            const joinPayload = joinResponse.data;

            ensureRemoteAudioContext();

            try {
                state.room = await TwilioVideo.connect(joinPayload.twilio_token, {
                    name: joinPayload.twilio_room_name,
                    audio: config.capabilities?.hiddenParticipant ? false : true,
                    video: config.capabilities?.hiddenParticipant
                        ? false
                        : {
                            name: LIVE_STREAM_CAMERA_TRACK_NAME,
                        },
                    dominantSpeaker: true,
                });
            } catch (error) {
                if (config.capabilities?.hiddenParticipant || !shouldRetryLiveStreamConnectWithoutCamera(error)) {
                    throw error;
                }

                state.room = await TwilioVideo.connect(joinPayload.twilio_token, {
                    name: joinPayload.twilio_room_name,
                    audio: true,
                    video: false,
                    dominantSpeaker: true,
                });

                if (deviceStatus instanceof HTMLElement) {
                    deviceStatus.textContent = 'Videocamera non disponibile. La diretta partirà con il solo microfono.';
                }
            }

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
            startButton.textContent = config.capabilities?.canManageBroadcast ? 'Avvia live' : 'Entra nella diretta';
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

    async function publishPoll() {
        if (
            !(pollForm instanceof HTMLFormElement) ||
            !(pollQuestionInput instanceof HTMLTextAreaElement) ||
            !(pollSubmitButton instanceof HTMLButtonElement) ||
            !config.routes.pollsStore
        ) {
            return;
        }

        const question = pollQuestionInput.value.trim();
        const options = pollOptionInputs
            .filter((input) => input instanceof HTMLInputElement)
            .map((input) => input.value.trim())
            .filter(Boolean);

        if (!question) {
            setPollFeedback('Inserisci la domanda del sondaggio.', true);
            return;
        }

        if (options.length < 2) {
            setPollFeedback('Inserisci almeno due risposte.', true);
            return;
        }

        pollQuestionInput.disabled = true;
        pollOptionInputs.forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.disabled = true;
            }
        });
        pollSubmitButton.disabled = true;
        setPollFeedback('Pubblicazione in corso...', false);

        try {
            await window.axios.post(config.routes.pollsStore, {
                question,
                options,
            });

            pollForm.reset();
            setPollComposerExpanded(false);
            await fetchState();
        } catch (error) {
            setPollComposerExpanded(true);
            const message = error?.response?.data?.errors?.question?.[0]
                ?? error?.response?.data?.errors?.options?.[0]
                ?? error?.response?.data?.message
                ?? 'Impossibile pubblicare il sondaggio.';
            setPollFeedback(message, true);
            console.error(error);
        } finally {
            updatePollComposerState();
        }
    }

    async function uploadDocument() {
        if (
            !(documentForm instanceof HTMLFormElement) ||
            !(documentInput instanceof HTMLInputElement) ||
            !config.routes.uploadDocument
        ) {
            return;
        }

        const file = documentInput.files?.[0] ?? null;

        if (!file) {
            setDocumentFeedback('Seleziona un PDF da caricare.', true);
            return;
        }

        const payload = new FormData();
        payload.append('document', file);

        if (documentSubmitButton instanceof HTMLButtonElement) {
            documentSubmitButton.disabled = true;
        }

        setDocumentFeedback('Caricamento in corso...', false);

        try {
            await window.axios.post(config.routes.uploadDocument, payload, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            documentForm.reset();
            setDocumentFeedback('PDF caricato con successo.', false);
            await fetchState();
        } catch (error) {
            const message = error?.response?.data?.errors?.document?.[0]
                ?? error?.response?.data?.message
                ?? 'Impossibile caricare il PDF.';
            setDocumentFeedback(message, true);
            console.error(error);
        } finally {
            if (documentSubmitButton instanceof HTMLButtonElement) {
                documentSubmitButton.disabled = false;
            }
        }
    }

    async function deleteDocument(documentItem) {
        if (!documentItem?.id || !config.routes.deleteDocumentTemplate) {
            return;
        }

        try {
            await window.axios.delete(
                config.routes.deleteDocumentTemplate.replace('__DOCUMENT__', String(documentItem.id)),
            );
            setDocumentFeedback('PDF rimosso con successo.', false);
            await fetchState();
        } catch (error) {
            setDocumentFeedback('Impossibile rimuovere il PDF.', true);
            console.error(error);
        }
    }

    function setDocumentFeedback(message, isError) {
        if (!(documentFeedback instanceof HTMLElement)) {
            return;
        }

        documentFeedback.textContent = message ?? '';
        documentFeedback.classList.toggle('text-error', isError);
        documentFeedback.classList.toggle('text-success', !isError && Boolean(message));
    }

    function setPollFeedback(message, isError) {
        if (!(pollFeedback instanceof HTMLElement)) {
            return;
        }

        pollFeedback.textContent = message ?? '';
        pollFeedback.classList.toggle('text-error', isError);
        pollFeedback.classList.toggle('text-success', !isError && Boolean(message));
    }

    function renderPolls(polls) {
        const pollsContainer = root.querySelector('[data-live-stream-polls-list]');
        const emptyStateElement = root.querySelector('[data-live-stream-polls-empty]');

        if (!(pollsContainer instanceof HTMLElement)) {
            return;
        }

        pollsContainer.replaceChildren();

        if (emptyStateElement instanceof HTMLElement) {
            emptyStateElement.classList.toggle('hidden', polls.length > 0);
        }

        polls.forEach((poll) => {
            const card = document.createElement('article');
            card.className = 'rounded-box border border-base-300 bg-base-100 p-4 shadow-sm';

            const header = document.createElement('div');
            header.className = 'flex flex-wrap items-start justify-between gap-3';

            const titleWrapper = document.createElement('div');
            titleWrapper.className = 'space-y-1';

            const badge = document.createElement('span');
            badge.className = `badge ${poll.is_open ? 'badge-success' : 'badge-outline'}`;
            badge.textContent = poll.is_open ? 'Aperto' : 'Chiuso';

            const question = document.createElement('h4');
            question.className = 'text-sm font-semibold text-base-content';
            question.textContent = poll.question;

            const meta = document.createElement('p');
            meta.className = 'text-xs text-base-content/60';
            meta.textContent = `${poll.total_responses} ${poll.total_responses === 1 ? 'risposta' : 'risposte'}${poll.published_at ? ` • ${formatPollTimestamp(poll.published_at)}` : ''}`;

            titleWrapper.append(badge, question, meta);
            header.appendChild(titleWrapper);

            if (poll.is_open && config.routes.closePollTemplate) {
                const closeButton = document.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'btn btn-outline btn-sm';
                closeButton.textContent = 'Termina invio';
                closeButton.addEventListener('click', async () => {
                    closeButton.disabled = true;

                    try {
                        await window.axios.patch(config.routes.closePollTemplate.replace('__POLL__', String(poll.id)));
                        await fetchState();
                    } catch (error) {
                        closeButton.disabled = false;
                        console.error(error);
                    }
                });
                header.appendChild(closeButton);
            }

            const optionsList = document.createElement('div');
            optionsList.className = 'mt-4 space-y-3';

            (poll.options ?? []).forEach((option) => {
                const optionCard = document.createElement('div');
                optionCard.className = 'relative overflow-hidden rounded-box border border-base-300 bg-base-200';

                const fill = document.createElement('div');
                fill.className = 'pointer-events-none absolute inset-y-0 left-0 bg-success/20';
                fill.style.width = `${Number(option.percentage) || 0}%`;

                const content = document.createElement('div');
                content.className = 'relative flex items-center justify-between gap-3 px-4 py-3';

                const label = document.createElement('p');
                label.className = 'text-sm font-medium text-base-content';
                label.textContent = option.label;

                const stats = document.createElement('p');
                stats.className = 'shrink-0 text-xs font-semibold text-base-content/70';
                stats.textContent = `${formatPercentage(option.percentage)} • ${option.responses_count}`;

                content.append(label, stats);
                optionCard.append(fill, content);
                optionsList.appendChild(optionCard);
            });

            card.append(header, optionsList);
            pollsContainer.appendChild(card);
        });
    }

    function formatPollTimestamp(value) {
        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return 'Pubblicato ora';
        }

        return new Intl.DateTimeFormat('it-IT', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    function formatPercentage(value) {
        const numericValue = Number(value) || 0;

        return Number.isInteger(numericValue)
            ? `${numericValue}%`
            : `${numericValue.toFixed(1).replace('.', ',')}%`;
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
        const participants = config.streamMode === 'mux_regia'
            ? [
                ...(state.latestState?.teacher_participants ?? []),
                ...(state.latestState?.participants ?? []),
            ]
            : (state.latestState?.participants ?? []);

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

    function renderMuxPlayer() {
        if (!(muxStage instanceof HTMLElement)) {
            return;
        }

        renderMuxStage(muxStage, state.latestState?.mux ?? config.mux, {
            title: 'Segnale video non disponibile',
            message: 'Il video comparira qui quando la regia avvia la trasmissione.',
            playerTitle: 'Player MUX',
        });
    }

    function syncRegiaModal(nextMux = null, nextCredentials = null) {
        if (!(regiaModal instanceof HTMLDialogElement)) {
            return;
        }

        if (nextMux && typeof nextMux === 'object') {
            config.mux = {
                ...(config.mux ?? {}),
                ...nextMux,
            };
        }

        if (nextCredentials && typeof nextCredentials === 'object') {
            config.mux = {
                ...(config.mux ?? {}),
                playbackId: nextCredentials.playbackId ?? config.mux?.playbackId,
                streamKey: nextCredentials.streamKey ?? config.mux?.streamKey,
                ingestUrl: nextCredentials.ingestUrl ?? config.mux?.ingestUrl,
            };
        }

        const mux = state.latestState?.mux ?? config.mux;
        const shouldShow = Boolean(config.capabilities?.canManageBroadcast && state.latestState?.status === 'live' && !mux?.isLive);

        if (regiaStreamKey instanceof HTMLElement) {
            regiaStreamKey.textContent = mux?.streamKey ?? 'n/d';
        }

        if (regiaIngestUrl instanceof HTMLElement) {
            regiaIngestUrl.textContent = mux?.ingestUrl ?? 'n/d';
        }

        if (regiaPlaybackId instanceof HTMLElement) {
            regiaPlaybackId.textContent = mux?.playbackId ?? 'n/d';
        }

        if (shouldShow || state.regiaModalOpen) {
            openRegiaModal();
        }
    }

    function openRegiaModal() {
        if (!(regiaModal instanceof HTMLDialogElement) || regiaModal.open) {
            return;
        }

        state.regiaModalOpen = true;
        regiaModal.showModal();
    }

    function closeRegiaModal() {
        if (!(regiaModal instanceof HTMLDialogElement) || !regiaModal.open) {
            return;
        }

        state.regiaModalOpen = false;
        regiaModal.close();
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
            startButton.textContent = config.capabilities?.canManageBroadcast ? 'Avvia diretta' : 'Entra nella diretta';
            startButton.classList.remove('hidden');
        }

        if (endButton instanceof HTMLButtonElement) {
            endButton.classList.add('hidden');
        }

        syncMicToggleButton();
        syncScreenShareButton();
    }
}
