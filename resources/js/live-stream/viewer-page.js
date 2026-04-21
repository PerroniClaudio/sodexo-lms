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
    renderChatMessages,
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
    };

    const previewController = createPreviewController(root);
    const joinButton = root.querySelector('[data-live-stream-join-button]');
    const handRaiseButton = root.querySelector('[data-live-stream-hand-raise-button]');
    const chatForm = root.querySelector('[data-live-stream-chat-form]');
    const chatInput = root.querySelector('[data-live-stream-chat-input]');
    const chatSubmitButton = root.querySelector('[data-live-stream-chat-submit]');

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
            `<div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 py-3 text-sm font-semibold text-white">${teacher.name}</div>`,
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

        const participants = (payload?.participants ?? []).filter((participant) => participant.user_id !== currentUserId());
        const seed = Math.floor(Date.now() / 60000);
        const selected = deterministicShuffle(participants, seed).slice(0, 5);

        selected.forEach((participant) => {
            const highlighted = state.dominantSpeakerIdentity === participant.twilio_identity;
            const track = getRemoteVideoTrack(participant.twilio_identity);

            if (!track) {
                strip.appendChild(
                    createPlaceholderCard(participant.name, 'Discente', {
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
