import * as TwilioVideo from 'twilio-video';
import {
    getTeacherStageVideoPublication,
    getRemoteVideoTrackByIdentity,
    isScreenSharePublication,
} from './participant-utils.mjs';
import {
    createPlaceholderCard,
    createPreviewController,
    deterministicShuffle,
    getLiveStreamConfig,
    getLiveStreamIconButtonContent,
    getParticipantAudioStatusMarkup,
    getLiveStreamRoot,
    renderMuxStage,
    renderChatMessages,
    renderDocuments,
    shouldRetryLiveStreamConnectWithoutCamera,
} from './shared';
import { LIVE_STREAM_CAMERA_TRACK_NAME } from './track-names.mjs';

export function initViewerPage() {
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
        dominantSpeakerIdentity: null,
        audioNodes: new Map(),
        pollingHandle: null,
        presenceHandle: null,
        activePollId: null,
        activePollSelection: null,
    };

    const previewController = createPreviewController(root);
    const joinButton = root.querySelector('[data-live-stream-join-button]');
    const handRaiseButton = root.querySelector('[data-live-stream-hand-raise-button]');
    const chatForm = root.querySelector('[data-live-stream-chat-form]');
    const chatInput = root.querySelector('[data-live-stream-chat-input]');
    const chatSubmitButton = root.querySelector('[data-live-stream-chat-submit]');
        const pollModal = root.querySelector('[data-live-stream-poll-modal]');
        const pollForm = root.querySelector('[data-live-stream-poll-form]');
        const pollQuestion = root.querySelector('[data-live-stream-poll-question]');
        const pollOptions = root.querySelector('[data-live-stream-poll-options]');
        const pollSubmitButton = root.querySelector('[data-live-stream-poll-submit]');
        const pollError = root.querySelector('[data-live-stream-poll-error]');
    const deviceStatus = root.querySelector('[data-live-stream-device-status]');

    renderChatMessages(root, []);

    if (joinButton instanceof HTMLButtonElement) {
        joinButton.addEventListener('click', async () => {
            await joinRoom();
        });
    }

    if (handRaiseButton instanceof HTMLButtonElement) {
        handRaiseButton.addEventListener('click', async () => {
            await toggleHandRaise();
        });
    }

    if (chatForm instanceof HTMLFormElement) {
        chatForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await sendChatMessage();
        });
    }

    if (pollForm instanceof HTMLFormElement) {
        pollForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await submitPollResponse();
        });
    }

    startPolling();

    window.addEventListener('beforeunload', () => {
        previewController.destroy();
        teardownRoom();
    });

    async function startPolling() {
        await fetchState();

        state.pollingHandle = window.setInterval(() => {
            void fetchState();
        }, config.pollIntervals.state);
    }

    async function fetchState() {
        try {
            const response = await window.axios.get(config.routes.state);
            state.latestState = response.data;

            updateViewState();
            syncViewerAudioGrant();
            renderViewerStage();
            renderParticipantList();
            renderChatMessages(root, response.data.messages ?? [], {
                canModerateMessages: canModerateMessages(),
                onDeleteMessage: deleteChatMessage,
            });
            renderDocuments(root, response.data.documents ?? []);
                renderActivePoll(response.data.active_poll ?? null);

            if (response.data.status !== 'live' && state.room) {
                teardownRoom();
            }
        } catch (error) {
            console.error(error);
        }
    }

    function updateViewState() {
        const payload = state.latestState;

        if (!payload) {
            return;
        }

        if (joinButton instanceof HTMLButtonElement) {
            joinButton.disabled = payload.status !== 'live' || state.joined;
            joinButton.textContent = state.joined ? 'Collegato' : 'Entra nella diretta';
        }

        updateHandRaiseState();
        updateChatComposerState();
            renderActivePoll(payload.active_poll ?? null);
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

    async function joinRoom() {
        if (state.joined) {
            return;
        }

        if (!(joinButton instanceof HTMLButtonElement)) {
            return;
        }

        joinButton.disabled = true;
        joinButton.textContent = 'Connessione...';

        try {
            const joinResponse = await window.axios.post(config.routes.join);
            const joinPayload = joinResponse.data;

            try {
                state.room = await TwilioVideo.connect(joinPayload.twilio_token, {
                    name: joinPayload.twilio_room_name,
                    audio: config.role === 'tutor' ? false : true,
                    video: config.role === 'tutor'
                        ? false
                        : {
                            name: LIVE_STREAM_CAMERA_TRACK_NAME,
                        },
                    dominantSpeaker: true,
                });
            } catch (error) {
                if (config.role === 'tutor' || !shouldRetryLiveStreamConnectWithoutCamera(error)) {
                    throw error;
                }

                state.room = await TwilioVideo.connect(joinPayload.twilio_token, {
                    name: joinPayload.twilio_room_name,
                    audio: true,
                    video: false,
                    dominantSpeaker: true,
                });

                if (deviceStatus instanceof HTMLElement) {
                    deviceStatus.textContent = 'Videocamera non disponibile. Sei entrato nella diretta con il solo microfono.';
                }
            }

            if (config.role === 'user') {
                state.room.localParticipant.audioTracks.forEach((publication) => {
                    publication.track.disable();
                });
            }

            subscribeToRoom();
            state.joined = true;

            state.presenceHandle = window.setInterval(() => {
                void sendPresence();
            }, config.pollIntervals.presence);

            await sendPresence();
            await fetchState();
        } catch (error) {
            joinButton.disabled = false;
            joinButton.textContent = 'Entra nella diretta';
            console.error(error);
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

    async function deleteChatMessage(message) {
        if (!message?.id || !config.routes.deleteMessageTemplate || !canModerateMessages()) {
            return;
        }

        try {
            await window.axios.delete(
                config.routes.deleteMessageTemplate.replace('__MESSAGE__', String(message.id)),
            );
            await fetchState();
        } catch (error) {
            console.error(error);
        }
    }

    function canModerateMessages() {
        return Boolean(config.capabilities?.canModerateChat) && Boolean(config.routes.deleteMessageTemplate) && state.joined && state.latestState?.status === 'live';
    }

        function renderActivePoll(poll) {
            if (
                !(pollModal instanceof HTMLElement) ||
                !(pollQuestion instanceof HTMLElement) ||
                !(pollOptions instanceof HTMLElement) ||
                !(pollSubmitButton instanceof HTMLButtonElement)
            ) {
                return;
            }

            const shouldShow = Boolean(
                config.role === 'user'
                && state.joined
                && state.latestState?.status === 'live'
                && poll,
            );

            if (!shouldShow) {
                state.activePollId = null;
                state.activePollSelection = null;
                pollModal.classList.add('hidden');
                pollModal.classList.remove('flex');
                pollQuestion.textContent = '';
                pollOptions.replaceChildren();
                setPollError('');
                pollSubmitButton.disabled = false;

                return;
            }

            if (state.activePollId !== poll.id) {
                state.activePollId = poll.id;
                state.activePollSelection = null;
                setPollError('');
            }

            pollModal.classList.remove('hidden');
            pollModal.classList.add('flex');
            pollQuestion.textContent = poll.question;
            pollOptions.replaceChildren();

            (poll.options ?? []).forEach((option) => {
                const label = document.createElement('label');
                label.className = 'flex cursor-pointer items-start gap-3 rounded-box border border-base-300 bg-base-100 px-4 py-3 transition hover:border-primary/40 hover:bg-primary/5';

                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'answer_index';
                radio.value = String(option.index);
                radio.className = 'radio radio-primary mt-0.5';
                radio.checked = state.activePollSelection === option.index;
                radio.addEventListener('change', () => {
                    state.activePollSelection = option.index;
                    setPollError('');
                    updatePollSubmitState();
                });

                const text = document.createElement('span');
                text.className = 'text-sm font-medium text-base-content';
                text.textContent = option.label;

                label.append(radio, text);
                pollOptions.appendChild(label);
            });

            updatePollSubmitState();
        }

        function updatePollSubmitState() {
            if (!(pollSubmitButton instanceof HTMLButtonElement)) {
                return;
            }

            pollSubmitButton.disabled = state.activePollSelection === null;
        }

        function setPollError(message) {
            if (!(pollError instanceof HTMLElement)) {
                return;
            }

            pollError.textContent = message;
            pollError.classList.toggle('hidden', !message);
        }

        async function submitPollResponse() {
            if (
                !(pollSubmitButton instanceof HTMLButtonElement) ||
                state.activePollId === null ||
                state.activePollSelection === null ||
                !config.routes.pollResponseTemplate
            ) {
                return;
            }

            pollSubmitButton.disabled = true;
            setPollError('');

            try {
                await window.axios.post(
                    config.routes.pollResponseTemplate.replace('__POLL__', String(state.activePollId)),
                    { answer_index: state.activePollSelection },
                );

                state.activePollId = null;
                state.activePollSelection = null;
                renderActivePoll(null);
                await fetchState();
            } catch (error) {
                const message = error?.response?.data?.errors?.answer_index?.[0]
                    ?? error?.response?.data?.message
                    ?? 'Impossibile inviare la risposta.';
                setPollError(message);
                pollSubmitButton.disabled = false;
                console.error(error);
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
            renderViewerStage();
        });

        state.room.on('participantDisconnected', (participant) => {
            detachParticipantAudio(participant);
            renderViewerStage();
        });

        state.room.on('dominantSpeakerChanged', (participant) => {
            state.dominantSpeakerIdentity = participant?.identity ?? null;
            renderViewerStage();
            renderParticipantList();
        });

        state.room.on('disconnected', () => {
            teardownRoom();
            renderViewerStage();
        });
    }

    function subscribeToParticipant(participant) {
        participant.tracks.forEach((publication) => {
            if (publication.isSubscribed) {
                handleTrackSubscribed(publication.track, participant);
            }

            publication.on('subscribed', (track) => handleTrackSubscribed(track, participant));
            publication.on('unsubscribed', (track) => handleTrackUnsubscribed(track, participant));
        });
    }

    function handleTrackSubscribed(track, participant) {
        if (track.kind === 'audio') {
            attachAudioTrack(track, participant);
            return;
        }

        renderViewerStage();
    }

    function handleTrackUnsubscribed(track, participant) {
        if (track.kind === 'audio') {
            detachAudioTrack(track.sid);
        }

        if (track.kind === 'video') {
            renderViewerStage();
        }

        if (!participant) {
            return;
        }
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
    }

    function detachParticipantAudio(participant) {
        participant.audioTracks.forEach((publication) => {
            if (publication.track) {
                detachAudioTrack(publication.track.sid);
            }
        });
    }

    function detachAudioTrack(trackSid) {
        const audioElement = state.audioNodes.get(trackSid);

        if (audioElement instanceof HTMLElement) {
            audioElement.remove();
        }

        state.audioNodes.delete(trackSid);
    }

    function getRemoteVideoTrack(identity) {
        return getRemoteVideoTrackByIdentity(state.room, identity);
    }

    function renderViewerStage() {
        renderMainTeacherFeed();
        renderStudentStrip();
    }

    function renderMainTeacherFeed() {
        const mainStage = root.querySelector('[data-live-stream-main-stage]');
        const teacher = state.latestState?.teacher;

        if (!(mainStage instanceof HTMLElement)) {
            return;
        }

        if (config.streamMode === 'mux_regia') {
            renderMuxStage(mainStage, state.latestState?.mux ?? config.mux, {
                title: 'Feed live non disponibile',
                message: 'Il player MUX comparira qui quando la regia avvia la trasmissione.',
                playerTitle: 'Diretta live',
            });

            return;
        }

        mainStage.replaceChildren();

        if (!teacher) {
            mainStage.appendChild(
                createPlaceholderCard('Docente non connesso', '', {
                    centered: true,
                    hideInitials: true,
                    className: 'h-full w-full min-h-0 rounded-[1.75rem] border-0 bg-[#24285f] px-8 text-white shadow-none',
                    footer: 'Il feed apparira qui appena il docente entra in diretta',
                }),
            );
            return;
        }

        const publication = getTeacherStageVideoPublication(state.room, teacher.twilio_identity);
        const track = publication?.track ?? null;
        const isScreenShareActive = isScreenSharePublication(publication);

        if (!track) {
            mainStage.appendChild(
                createPlaceholderCard(teacher.name, 'Docente', {
                    centered: true,
                    hideInitials: true,
                    className: 'h-full w-full min-h-0 rounded-[1.75rem] border-0 bg-[#24285f] px-8 text-white shadow-none',
                    footer: 'In attesa del video del docente',
                }),
            );
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'relative h-full w-full overflow-hidden rounded-[1.75rem] bg-black';

        const videoElement = document.createElement('video');
        videoElement.className = `h-full w-full ${isScreenShareActive ? 'object-contain' : 'object-cover'}`;
        videoElement.autoplay = true;
        videoElement.playsInline = true;
        track.attach(videoElement);

        wrapper.appendChild(videoElement);
        if (isScreenShareActive) {
            wrapper.insertAdjacentHTML(
                'beforeend',
                '<div class="absolute left-4 top-4 rounded-full bg-black/70 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">Schermo condiviso</div>',
            );
        }
        wrapper.insertAdjacentHTML(
            'beforeend',
            `<div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/70 to-transparent px-4 py-3 text-sm font-semibold text-white">${teacher.name}</div>`,
        );

        mainStage.appendChild(wrapper);
    }

    function renderStudentStrip() {
        const strip = root.querySelector('[data-live-stream-strip]');
        const payload = state.latestState;

        if (!(strip instanceof HTMLElement)) {
            return;
        }

        strip.replaceChildren();

        const selected = config.streamMode === 'mux_regia'
            ? (payload?.viewer_roster ?? []).filter((participant) => participant.user_id !== currentUserId()).slice(0, 5)
            : deterministicShuffle(
                (payload?.participants ?? []).filter((participant) => participant.user_id !== currentUserId()),
                Math.floor(Date.now() / 60000),
            ).slice(0, 5);

        selected.forEach((participant) => {
            const highlighted = state.dominantSpeakerIdentity === participant.twilio_identity;
            const track = getRemoteVideoTrack(participant.twilio_identity);

            if (!track) {
                strip.appendChild(
                    createPlaceholderCard(participant.name, participant.app_role === 'teacher' ? 'Docente' : 'Discente', {
                        initials: participant.initials,
                        highlighted,
                        className: 'bg-base-100 text-base-content shadow-none',
                    }),
                );

                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-sm';

            if (highlighted) {
                wrapper.classList.add('border-success', 'ring-2', 'ring-success/40');
            }

            const videoElement = document.createElement('video');
            videoElement.className = 'aspect-video w-full bg-black object-cover';
            videoElement.autoplay = true;
            videoElement.playsInline = true;
            track.attach(videoElement);

            wrapper.appendChild(videoElement);
            wrapper.insertAdjacentHTML(
                'beforeend',
                `<div class="border-t border-base-300 bg-base-100 px-3 py-2 text-sm font-medium text-base-content">${participant.name}</div>`,
            );

            strip.appendChild(wrapper);
        });
    }

    function renderParticipantList() {
        const list = root.querySelector('[data-live-stream-participant-list]');
        const count = root.querySelector('[data-live-stream-participant-count]');
        const participants = state.latestState?.participants ?? [];

        if (!(list instanceof HTMLElement)) {
            return;
        }

        list.replaceChildren();

        if (count instanceof HTMLElement) {
            count.textContent = `${participants.length}`;
        }

        participants.forEach((participant) => {
            const isSpeaking = state.dominantSpeakerIdentity === participant.twilio_identity;
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 px-3 py-2';

            item.innerHTML = `
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-base-200 text-sm font-semibold text-base-content ${isSpeaking ? 'ring-2 ring-success' : ''}">
                        ${participant.initials}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">${participant.name}</p>
                        <p class="text-xs text-base-content/60">${participant.user_id === currentUserId() ? 'Tu' : 'Discente'}</p>
                    </div>
                </div>
                <span class="text-xs text-base-content/50">
                    ${getParticipantAudioStatusMarkup(participant.audio_enabled)}
                </span>
            `;

            list.appendChild(item);
        });
    }

    function updateHandRaiseState() {
        if (!(handRaiseButton instanceof HTMLButtonElement)) {
            return;
        }

        const statusElement = root.querySelector('[data-live-stream-hand-raise-status]');
        const hasPendingHandRaise = Boolean(state.latestState?.current_hand_raise);

        handRaiseButton.classList.toggle('hidden', !state.joined);
        handRaiseButton.classList.toggle('btn-warning', hasPendingHandRaise);
        handRaiseButton.classList.toggle('btn-outline', !hasPendingHandRaise);
        handRaiseButton.classList.add('btn-square');
        handRaiseButton.setAttribute('aria-label', hasPendingHandRaise ? 'Annulla richiesta mano alzata' : 'Alza la mano');
        handRaiseButton.setAttribute('title', hasPendingHandRaise ? 'Annulla richiesta mano alzata' : 'Alza la mano');
        handRaiseButton.innerHTML = getLiveStreamIconButtonContent('hand', hasPendingHandRaise ? 'Annulla richiesta mano alzata' : 'Alza la mano');
        handRaiseButton.disabled = !state.joined || state.latestState?.status !== 'live';

        if (statusElement instanceof HTMLElement) {
            statusElement.textContent = hasPendingHandRaise
                ? 'Richiesta inviata al docente'
                : 'Nessuna richiesta attiva';
        }
    }

    async function toggleHandRaise() {
        if (!(handRaiseButton instanceof HTMLButtonElement) || handRaiseButton.disabled) {
            return;
        }

        try {
            if (state.latestState?.current_hand_raise) {
                await window.axios.delete(config.routes.cancelHandRaise);
            } else {
                await window.axios.post(config.routes.handRaise);
            }

            await fetchState();
        } catch (error) {
            console.error(error);
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

    function syncViewerAudioGrant() {
        if (!state.room || config.role !== 'user') {
            return;
        }

        const participant = (state.latestState?.participants ?? []).find((item) => item.user_id === currentUserId());
        const localAudioTrack = [...state.room.localParticipant.audioTracks.values()][0]?.track ?? null;

        if (!participant || !localAudioTrack) {
            return;
        }

        if (participant.audio_enabled) {
            localAudioTrack.enable();
        } else {
            localAudioTrack.disable();
        }
    }

    function currentUserId() {
        const identity = state.room?.localParticipant.identity ?? null;
        const fromIdentity = identity?.split(':').pop();

        if (fromIdentity) {
            return Number.parseInt(fromIdentity, 10);
        }

        return -1;
    }

    function teardownRoom() {
        if (state.presenceHandle) {
            window.clearInterval(state.presenceHandle);
            state.presenceHandle = null;
        }

        if (state.room) {
            const activeRoom = state.room;
            state.room = null;
            activeRoom.disconnect();
        }

        state.audioNodes.forEach((node) => node.remove());
        state.audioNodes.clear();
        state.joined = false;
    }
}
