import { Hand, Mic, MicOff, Pin, ScreenShare } from 'lucide';

const fakeMessages = [
    {
        author: 'Tutor Aula',
        role: 'tutor',
        time: '09:58',
        body: 'Benvenuti, la live iniziera tra pochi minuti.',
    },
    {
        author: 'Giulia Rossi',
        role: 'student',
        time: '10:00',
        body: 'Buongiorno, si sente correttamente.',
    },
    {
        author: 'Docente',
        role: 'teacher',
        time: '10:01',
        body: 'Perfetto, iniziamo con una panoramica introduttiva del modulo.',
    },
];

const LIVE_STREAM_ICON_NODES = {
    hand: Hand,
    mic: Mic,
    'mic-off': MicOff,
    pin: Pin,
    'screen-share': ScreenShare,
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

export function renderStaticChat(root) {
    const messagesContainer = root.querySelector('[data-live-stream-chat-messages]');
    const messageTemplate = root.querySelector('[data-live-stream-chat-template]');

    if (!(messagesContainer instanceof HTMLElement) || !(messageTemplate instanceof HTMLTemplateElement)) {
        return;
    }

    messagesContainer.replaceChildren();

    fakeMessages.forEach((message) => {
        const fragment = messageTemplate.content.cloneNode(true);

        if (!(fragment instanceof DocumentFragment)) {
            return;
        }

        const authorElement = fragment.querySelector('[data-chat-author]');
        const timeElement = fragment.querySelector('[data-chat-time]');
        const bodyElement = fragment.querySelector('[data-chat-body]');
        const initialsElement = fragment.querySelector('[data-chat-initials]');
        const bubbleElement = fragment.querySelector('[data-chat-bubble]');

        if (
            !(authorElement instanceof HTMLElement) ||
            !(timeElement instanceof HTMLElement) ||
            !(bodyElement instanceof HTMLElement) ||
            !(initialsElement instanceof HTMLElement) ||
            !(bubbleElement instanceof HTMLElement)
        ) {
            return;
        }

        authorElement.textContent = message.author;
        timeElement.textContent = message.time;
        bodyElement.textContent = message.body;
        initialsElement.textContent = getInitials(message.author);

        if (message.role === 'teacher' || message.role === 'tutor') {
            bubbleElement.classList.add('bg-primary', 'text-primary-content');
            bubbleElement.classList.remove('bg-base-200');
        }

        messagesContainer.appendChild(fragment);
    });
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
            statusElement.textContent = 'Consenti l’accesso a videocamera e microfono per visualizzare l’anteprima.';
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
                statusElement.textContent = hasVideoTrack ? 'Anteprima attiva. Videocamera e microfono sono collegati.' : '';
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
