import { Hand, Mic, MicOff, Pin, ScreenShare, Trash2 } from 'lucide';

const LIVE_STREAM_ICON_NODES = {
    hand: Hand,
    mic: Mic,
    'mic-off': MicOff,
    pin: Pin,
    'screen-share': ScreenShare,
    trash: Trash2,
};

export function getLiveStreamRoot() {
    return document.querySelector('[data-live-stream-root]');
}

export function getLiveStreamConfig(root) {
    const configElement = root?.querySelector('[data-live-stream-config]');

    if (!(configElement instanceof HTMLScriptElement)) {
        return null;
    }

    try {
        return JSON.parse(configElement.textContent ?? '{}');
    } catch (error) {
        console.error(error);

        return null;
    }
}

export function renderChatMessages(root, messages = [], options = {}) {
    const messagesContainer = root.querySelector('[data-live-stream-chat-messages]');
    const messageTemplate = root.querySelector('[data-live-stream-chat-template]');

    if (!(messagesContainer instanceof HTMLElement) || !(messageTemplate instanceof HTMLTemplateElement)) {
        return;
    }

    const shouldStickToBottom = Math.abs(
        messagesContainer.scrollHeight - messagesContainer.clientHeight - messagesContainer.scrollTop,
    ) < 24;

    messagesContainer.replaceChildren();

    messages.forEach((message) => {
        const fragment = messageTemplate.content.cloneNode(true);

        if (!(fragment instanceof DocumentFragment)) {
            return;
        }

        const authorElement = fragment.querySelector('[data-chat-author]');
        const timeElement = fragment.querySelector('[data-chat-time]');
        const bodyElement = fragment.querySelector('[data-chat-body]');
        const initialsElement = fragment.querySelector('[data-chat-initials]');
        const bubbleElement = fragment.querySelector('[data-chat-bubble]');
        const deleteButton = fragment.querySelector('[data-chat-delete]');

        if (
            !(authorElement instanceof HTMLElement) ||
            !(timeElement instanceof HTMLElement) ||
            !(bodyElement instanceof HTMLElement) ||
            !(initialsElement instanceof HTMLElement) ||
            !(bubbleElement instanceof HTMLElement)
        ) {
            return;
        }

        authorElement.textContent = message.name;
        timeElement.textContent = formatChatMessageTime(message.sent_at);
        bodyElement.textContent = message.body;
        initialsElement.textContent = message.initials || getInitials(message.name);

        if (message.app_role === 'teacher' || message.app_role === 'tutor') {
            bubbleElement.classList.add('bg-primary', 'text-primary-content');
            bubbleElement.classList.remove('bg-base-200', 'bg-base-100', 'text-base-content');
        }

        if (deleteButton instanceof HTMLButtonElement) {
            const canModerateMessages = Boolean(options.canModerateMessages);

            deleteButton.classList.toggle('hidden', !canModerateMessages);
            deleteButton.disabled = !canModerateMessages;

            if (canModerateMessages && typeof options.onDeleteMessage === 'function') {
                deleteButton.addEventListener('click', async () => {
                    await options.onDeleteMessage(message);
                });
            }
        }

        messagesContainer.appendChild(fragment);
    });

    if (shouldStickToBottom) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
}

export function renderDocuments(root, documents = [], options = {}) {
    const documentsContainer = root.querySelector('[data-live-stream-documents-list]');
    const emptyStateElement = root.querySelector('[data-live-stream-documents-empty]');

    if (!(documentsContainer instanceof HTMLElement)) {
        return;
    }

    documentsContainer.replaceChildren();

    if (emptyStateElement instanceof HTMLElement) {
        emptyStateElement.classList.toggle('hidden', documents.length > 0);
    }

    documents.forEach((documentItem) => {
        const item = document.createElement('article');
        item.className = 'rounded-box border border-base-300 bg-base-100 p-4';

        const infoWrapper = document.createElement('div');
        infoWrapper.className = 'flex flex-col gap-3 md:flex-row md:items-start md:justify-between';

        const content = document.createElement('div');
        content.className = 'min-w-0';

        const title = document.createElement('p');
        title.className = 'truncate text-sm font-semibold';
        title.textContent = documentItem.name;

        const meta = document.createElement('p');
        meta.className = 'mt-1 text-xs text-base-content/60';
        meta.textContent = [
            formatFileSize(documentItem.size_bytes),
            formatUploadTime(documentItem.uploaded_at),
            documentItem.uploaded_by ? `Caricato da ${documentItem.uploaded_by}` : null,
        ].filter(Boolean).join(' • ');

        content.append(title, meta);

        const actions = document.createElement('div');
        actions.className = 'flex shrink-0 flex-wrap items-center gap-2';

        const downloadLink = document.createElement('a');
        downloadLink.className = 'btn btn-outline btn-sm';
        downloadLink.href = documentItem.download_url;
        downloadLink.target = '_blank';
        downloadLink.rel = 'noopener';
        downloadLink.textContent = 'Scarica';

        actions.appendChild(downloadLink);

        const canDeleteDocument = Boolean(options.canDeleteDocuments) && typeof options.onDeleteDocument === 'function';

        if (canDeleteDocument) {
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn btn-ghost btn-sm text-error';
            deleteButton.textContent = 'Rimuovi';
            deleteButton.addEventListener('click', async () => {
                await options.onDeleteDocument(documentItem);
            });
            actions.appendChild(deleteButton);
        }

        infoWrapper.append(content, actions);
        item.appendChild(infoWrapper);
        documentsContainer.appendChild(item);
    });
}

export function renderMuxStage(stageElement, mux, options = {}) {
    if (!(stageElement instanceof HTMLElement)) {
        return;
    }

    const title = options.title ?? 'Feed live non disponibile';
    const message = options.message ?? 'Il player MUX comparira qui quando la regia avvia la trasmissione.';
    const playerTitle = options.playerTitle ?? 'Player MUX';
    const accentColor = resolveMuxAccentColor(stageElement);

    if (!mux?.playbackId) {
        renderMuxPlaceholder(stageElement, 'empty', title, message);

        return;
    }

    if (!mux.isLive && !options.showOfflinePlayer) {
        renderMuxPlaceholder(stageElement, 'offline', title, message);

        return;
    }

    const nextSignature = JSON.stringify({
        kind: 'player',
        playbackId: mux.playbackId,
        isLive: Boolean(mux.isLive),
        playerTitle,
        accentColor,
    });

    if (stageElement.dataset.muxStageSignature === nextSignature) {
        return;
    }

    stageElement.replaceChildren();
    stageElement.dataset.muxStageSignature = nextSignature;

    const wrapper = document.createElement('div');
    wrapper.className = 'relative h-full w-full overflow-hidden rounded-[1.75rem] bg-black';

    const iframe = document.createElement('iframe');
    iframe.className = 'h-full w-full border-0';
    iframe.allow = 'accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;';
    iframe.src = buildMuxPlayerUrl(mux.playbackId, accentColor);
    iframe.title = playerTitle;

    wrapper.appendChild(iframe);

    if (!mux.isLive) {
        wrapper.insertAdjacentHTML(
            'beforeend',
            '<div class="absolute left-4 top-4 rounded-full bg-black/70 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">Offline</div>',
        );
    }

    stageElement.appendChild(wrapper);
}

function buildMuxPlayerUrl(playbackId, accentColor) {
    const params = new URLSearchParams({
        autoplay: 'false',
        muted: 'false',
    });

    if (accentColor) {
        params.set('accent-color', accentColor);
    }

    return `https://player.mux.com/${playbackId}?${params.toString()}`;
}

function resolveMuxAccentColor(stageElement) {
    const sourceElement = stageElement.closest('[data-theme]') ?? document.documentElement;
    const color = getComputedStyle(sourceElement).getPropertyValue('--color-primary').trim();

    return color || null;
}

function renderMuxPlaceholder(stageElement, kind, title, message) {
    const nextSignature = JSON.stringify({
        kind,
        title,
        message,
    });

    if (stageElement.dataset.muxStageSignature === nextSignature) {
        return;
    }

    stageElement.replaceChildren();
    stageElement.dataset.muxStageSignature = nextSignature;
    stageElement.appendChild(createPlaceholderCard(title, '', {
        centered: true,
        hideInitials: true,
        className: 'h-full w-full min-h-0 rounded-[1.75rem] border-0 bg-[#24285f] px-8 text-white shadow-none',
        footer: message,
    }));
}

export function getLiveStreamIconSvg(iconName, classNames = 'h-4 w-4') {
    const iconNode = LIVE_STREAM_ICON_NODES[iconName];

    if (!iconNode) {
        return '';
    }

    const childrenMarkup = iconNode
        .map(([tagName, attributes]) => {
            const attrs = Object.entries(attributes)
                .map(([key, value]) => `${key}="${value}"`)
                .join(' ');

            return `<${tagName} ${attrs}></${tagName}>`;
        })
        .join('');

    return `
        <svg xmlns="http://www.w3.org/2000/svg" class="${classNames}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            ${childrenMarkup}
        </svg>
    `.trim();
}

export function getLiveStreamIconButtonContent(iconName, label, classNames = 'h-4 w-4') {
    return `${getLiveStreamIconSvg(iconName, classNames)}<span class="sr-only">${label}</span>`;
}

export function getParticipantAudioStatusMarkup(audioEnabled, classNames = 'h-4 w-4') {
    const label = audioEnabled ? 'Audio attivo' : 'Audio moderato';

    return `
        <span class="inline-flex items-center" aria-label="${label}" title="${label}">
            ${getLiveStreamIconButtonContent(audioEnabled ? 'mic' : 'mic-off', label, classNames)}
        </span>
    `.trim();
}

export function getInitials(name) {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function formatChatMessageTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('it-IT', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function formatUploadTime(value) {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function formatFileSize(value) {
    const size = Number(value);

    if (!Number.isFinite(size) || size <= 0) {
        return 'PDF';
    }

    return new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: size >= 1_000_000 ? 1 : 0,
    }).format(size >= 1_000_000 ? size / 1_000_000 : size / 1_000).concat(size >= 1_000_000 ? ' MB' : ' KB');
}

export function createPreviewController(root, options = {}) {
    const openButton = root.querySelector('[data-live-stream-preview-toggle]');
    const requestButton = root.querySelector('[data-live-stream-preview-request]');
    const panelElement = root.querySelector('[data-live-stream-preview-panel]');
    const previewContentElement = root.querySelector('[data-live-stream-preview-content]');
    const videoElement = root.querySelector('[data-live-stream-preview]');
    const meterElement = root.querySelector('[data-live-stream-mic-meter]');
    const micLabelElement = root.querySelector('[data-live-stream-mic-label]');
    const statusElement = root.querySelector('[data-live-stream-device-status]');
    const emptyStateElement = root.querySelector('[data-live-stream-preview-empty]');

    let mediaStream = null;
    let audioContext = null;
    let analyserNode = null;
    let animationFrameId = null;
    let microphoneSource = null;

    function hasVideoTrack() {
        return (mediaStream?.getVideoTracks().length ?? 0) > 0;
    }

    function notifyAudioStateChange() {
        if (typeof options.onAudioStateChange === 'function') {
            options.onAudioStateChange({
                hasAudioTrack: mediaStream?.getAudioTracks().length > 0,
                audioEnabled: mediaStream?.getAudioTracks()[0]?.enabled ?? false,
            });
        }
    }

    function updateRequestButton() {
        if (!(requestButton instanceof HTMLButtonElement)) {
            return;
        }

        requestButton.classList.toggle('hidden', mediaStream !== null);
    }

    function updatePreviewContentVisibility() {
        if (!(previewContentElement instanceof HTMLElement)) {
            return;
        }

        previewContentElement.classList.toggle('hidden', mediaStream === null);
    }

    function cleanupMeter() {
        if (animationFrameId !== null) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }

        if (microphoneSource) {
            microphoneSource.disconnect();
            microphoneSource = null;
        }

        if (analyserNode) {
            analyserNode.disconnect();
            analyserNode = null;
        }

        if (audioContext) {
            audioContext.close();
            audioContext = null;
        }
    }

    function resetPreview() {
        cleanupMeter();

        if (mediaStream) {
            mediaStream.getTracks().forEach((track) => track.stop());
            mediaStream = null;
        }

        if (videoElement instanceof HTMLVideoElement) {
            videoElement.srcObject = null;
            videoElement.classList.remove('hidden');
        }

        if (emptyStateElement instanceof HTMLElement) {
            emptyStateElement.classList.add('hidden');
            emptyStateElement.classList.remove('flex');
        }

        if (meterElement instanceof HTMLProgressElement) {
            meterElement.value = 0;
        }

        if (micLabelElement instanceof HTMLElement) {
            micLabelElement.textContent = '';
        }

        if (statusElement instanceof HTMLElement) {
            statusElement.textContent = 'Consenti l’accesso a videocamera e microfono per visualizzare l’anteprima. Puoi continuare anche senza videocamera.';
        }

        notifyAudioStateChange();
        updateRequestButton();
        updatePreviewContentVisibility();
    }

    function startMicrophoneMeter(stream) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;

        if (!AudioContextClass || !(meterElement instanceof HTMLProgressElement)) {
            return;
        }

        audioContext = new AudioContextClass();
        analyserNode = audioContext.createAnalyser();
        analyserNode.fftSize = 256;
        microphoneSource = audioContext.createMediaStreamSource(stream);
        microphoneSource.connect(analyserNode);

        const dataArray = new Uint8Array(analyserNode.frequencyBinCount);

        const updateMeter = () => {
            if (!analyserNode) {
                return;
            }

            analyserNode.getByteTimeDomainData(dataArray);

            let sum = 0;

            dataArray.forEach((value) => {
                const normalized = (value - 128) / 128;
                sum += normalized * normalized;
            });

            const rms = Math.sqrt(sum / dataArray.length);
            const level = Math.min(100, Math.round(rms * 220));

            meterElement.value = level;

            animationFrameId = requestAnimationFrame(updateMeter);
        };

        updateMeter();
    }

    async function openPreview() {
        if (!(videoElement instanceof HTMLVideoElement)) {
            return;
        }

        resetPreview();

        try {
            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                throw new Error('getUserMedia is not available');
            }

            try {
                mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
            } catch (error) {
                if (!(error instanceof DOMException) || error.name !== 'NotFoundError') {
                    throw error;
                }

                mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
            }

            const hasVideoTrack = mediaStream.getVideoTracks().length > 0;

            if (hasVideoTrack) {
                videoElement.srcObject = mediaStream;
                videoElement.classList.remove('hidden');

                if (emptyStateElement instanceof HTMLElement) {
                    emptyStateElement.classList.add('hidden');
                    emptyStateElement.classList.remove('flex');
                }

                await videoElement.play();
            } else {
                videoElement.srcObject = null;
                videoElement.classList.add('hidden');

                if (emptyStateElement instanceof HTMLElement) {
                    emptyStateElement.classList.remove('hidden');
                    emptyStateElement.classList.add('flex');
                }
            }

            if (statusElement instanceof HTMLElement) {
                statusElement.textContent = hasVideoTrack
                    ? 'Anteprima attiva. Videocamera e microfono sono collegati.'
                    : 'Microfono collegato. Nessuna videocamera disponibile: puoi comunque entrare nella diretta.';
            }

            startMicrophoneMeter(mediaStream);
            notifyAudioStateChange();
            updateRequestButton();
            updatePreviewContentVisibility();
        } catch (error) {
            if (statusElement instanceof HTMLElement) {
                statusElement.textContent =
                    error instanceof DOMException && error.name === 'NotFoundError'
                        ? 'Nessuna videocamera o nessun microfono disponibile su questo dispositivo.'
                        : 'Impossibile accedere a videocamera o microfono. Controlla i permessi del browser.';
            }

            console.error(error);
            notifyAudioStateChange();
            updateRequestButton();
            updatePreviewContentVisibility();
        }
    }

    if (openButton instanceof HTMLButtonElement && panelElement instanceof HTMLElement) {
        openButton.addEventListener('click', async () => {
            const isOpen = !panelElement.classList.contains('hidden');

            if (isOpen) {
                panelElement.classList.add('hidden');
                resetPreview();
                return;
            }

            panelElement.classList.remove('hidden');
            await openPreview();
        });
    }

    if (options.autoOpen && panelElement instanceof HTMLElement) {
        panelElement.classList.remove('hidden');
        void openPreview();
    }

    if (requestButton instanceof HTMLButtonElement) {
        requestButton.addEventListener('click', async () => {
            await openPreview();
        });
    }

    updateRequestButton();
    updatePreviewContentVisibility();

    return {
        async open() {
            await openPreview();
        },
        hasAudioTrack() {
            return mediaStream?.getAudioTracks().length > 0;
        },
        hasVideoTrack,
        isAudioEnabled() {
            return mediaStream?.getAudioTracks()[0]?.enabled ?? false;
        },
        toggleAudio() {
            const audioTrack = mediaStream?.getAudioTracks()[0] ?? null;

            if (!audioTrack) {
                return false;
            }

            audioTrack.enabled = !audioTrack.enabled;
            notifyAudioStateChange();

            return audioTrack.enabled;
        },
        destroy() {
            resetPreview();
        },
    };
}

export function shouldRetryLiveStreamConnectWithoutCamera(error) {
    if (!error || typeof error !== 'object') {
        return false;
    }

    const errorName = typeof error.name === 'string' ? error.name : '';
    const errorMessage = typeof error.message === 'string' ? error.message.toLowerCase() : '';

    if (errorName === 'NotFoundError' || errorName === 'DevicesNotFoundError' || errorName === 'OverconstrainedError') {
        return true;
    }

    return errorMessage.includes('video') && (
        errorMessage.includes('not found')
        || errorMessage.includes('notavailableerror')
        || errorMessage.includes('source unavailable')
        || errorMessage.includes('could not start video source')
    );
}

export function deterministicShuffle(items, seed) {
    return [...items].sort(
        (left, right) => hashString(`${seed}:${left.user_id}`) - hashString(`${seed}:${right.user_id}`),
    );
}

function hashString(value) {
    let hash = 0;

    for (let index = 0; index < value.length; index += 1) {
        hash = (hash << 5) - hash + value.charCodeAt(index);
        hash |= 0;
    }

    return hash;
}

export function setBadgeState(root, label, tone = 'badge-outline') {
    const badge = root.querySelector('[data-live-stream-status-badge]');

    if (!(badge instanceof HTMLElement)) {
        return;
    }

    badge.className = 'badge';
    badge.classList.add(tone);
    badge.textContent = label;
}

export function setMessage(root, message) {
    const messageElement = root.querySelector('[data-live-stream-message]');

    if (messageElement instanceof HTMLElement) {
        messageElement.textContent = message ?? '';
    }
}

export function getParticipantInitialsBadgeClassNames(highlighted = false) {
    return [
        'flex',
        'h-10',
        'w-10',
        'items-center',
        'justify-center',
        'rounded-full',
        'border-2',
        highlighted ? 'border-success' : 'border-transparent',
        highlighted ? 'ring-2' : '',
        highlighted ? 'ring-success/35' : '',
        highlighted ? 'bg-success/10' : 'bg-base-200',
        'text-sm',
        'font-semibold',
        'text-base-content',
    ].join(' ');
}

export function createPlaceholderCard(label, subtitle, options = {}) {
    const wrapper = document.createElement('div');
    wrapper.className = `flex min-h-[12rem] flex-col justify-between rounded-box border border-base-300 bg-neutral p-4 text-neutral-content ${options.className ?? ''}`.trim();

    if (options.highlighted) {
        wrapper.classList.add('border-success', 'ring-2', 'ring-success/40');
    }

    if (options.centered) {
        wrapper.classList.remove('justify-between');
        wrapper.classList.add('items-center', 'justify-center', 'text-center');
    }

    const avatarMarkup = options.hideInitials
        ? ''
        : `<div class="inline-flex h-12 w-12 items-center justify-center rounded-full border-2 ${options.highlighted ? 'border-success ring-2 ring-success/35 bg-success/10' : 'border-transparent bg-base-100/10'} text-sm font-semibold">
                ${options.initials ?? getInitials(label)}
            </div>`;

    wrapper.innerHTML = `
        <div class="${options.centered ? 'space-y-2' : 'space-y-2'}">
            ${avatarMarkup}
            <div>
                <p class="text-sm font-semibold">${label}</p>
                <p class="text-xs text-neutral-content/60">${subtitle ?? ''}</p>
                <p class="text-xs text-neutral-content/50">${options.footer ?? 'In attesa del video'}</p>
            </div>
        </div>
    `;

    return wrapper;
}
