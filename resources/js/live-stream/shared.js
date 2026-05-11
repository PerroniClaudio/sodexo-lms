import * as TwilioVideo from 'twilio-video';
import { Expand, Hand, Image as ImageIcon, Mic, MicOff, Pin, ScreenShare, ScreenShareOff, Shrink, Trash2 } from 'lucide';

const LIVE_STREAM_VIDEO_CONSTRAINTS = Object.freeze({
    width: 640,
    height: 480,
    frameRate: 24,
});

const LIVE_STREAM_VIDEO_PROCESSOR_ADD_OPTIONS = Object.freeze({
    inputFrameBufferType: 'videoframe',
    outputFrameBufferContextType: 'bitmaprenderer',
});

const LIVE_STREAM_VIDEO_PROCESSOR_ASSETS_PATH = '/twilio-video-processors-assets/';
let videoProcessorsModulePromise = null;

const LIVE_STREAM_ICON_NODES = {
    hand: Hand,
    image: ImageIcon,
    mic: Mic,
    'mic-off': MicOff,
    pin: Pin,
    expand: Expand,
    'screen-share': ScreenShare,
    'screen-share-off': ScreenShareOff,
    shrink: Shrink,
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

export function isFullscreenApiSupported(documentObject = globalThis.document) {
    return typeof documentObject?.exitFullscreen === 'function';
}

export function isElementInFullscreen(targetElement, documentObject = globalThis.document) {
    if (!targetElement || !documentObject?.fullscreenElement) {
        return false;
    }

    return documentObject.fullscreenElement === targetElement
        || targetElement.contains(documentObject.fullscreenElement);
}

export function getLiveStreamFullscreenToggleLabel(isFullscreenActive) {
    return isFullscreenActive ? 'Esci da schermo intero' : 'Schermo intero';
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

    const title = options.title ?? 'Segnale video non disponibile';
    const message = options.message ?? 'Il video comparira qui quando la regia avvia la trasmissione.';
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

    const muxPlayer = document.createElement('mux-player');
    muxPlayer.className = 'h-full w-full';
    muxPlayer.dataset.liveStreamMuxPlayer = 'true';
    muxPlayer.setAttribute('playback-id', mux.playbackId);
    muxPlayer.setAttribute('stream-type', mux.isLive ? 'live' : 'on-demand');
    muxPlayer.setAttribute('metadata-video-title', playerTitle);
    muxPlayer.setAttribute('accent-color', accentColor || '#2563eb');
    muxPlayer.setAttribute('primary-color', accentColor || '#2563eb');
    muxPlayer.setAttribute('muted', 'false');
    muxPlayer.setAttribute('autoplay', 'false');

    wrapper.appendChild(muxPlayer);

    if (!mux.isLive) {
        wrapper.insertAdjacentHTML(
            'beforeend',
            '<div class="absolute left-4 top-4 rounded-full bg-black/70 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">Offline</div>',
        );
    }

    stageElement.appendChild(wrapper);
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

export function isAudioOutputSelectionSupported(browser = globalThis) {
    const mediaDevices = browser?.navigator?.mediaDevices;
    const mediaElementPrototype = browser?.HTMLMediaElement?.prototype;

    return Boolean(mediaDevices?.enumerateDevices && typeof mediaElementPrototype?.setSinkId === 'function');
}

export function filterAudioOutputDevices(devices = []) {
    return devices.filter((device) => device?.kind === 'audiooutput');
}

export function formatAudioOutputDeviceLabel(device, index = 0) {
    const label = device?.label?.trim();

    if (label) {
        return label;
    }

    if (device?.deviceId === 'default') {
        return 'Predefinito di sistema';
    }

    return `Uscita audio ${index + 1}`;
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

export function isHardwareLikelySufficient(hardware = globalThis.navigator) {
    const ram = hardware?.deviceMemory;
    const cores = hardware?.hardwareConcurrency;
    const minimumRam = 8;
    const minimumCores = 4;

    if (ram !== undefined && ram < minimumRam) {
        return false;
    }

    if (cores !== undefined && cores < minimumCores) {
        return false;
    }

    return true;
}

export function isBackgroundProcessorBenchmarkSlow(frameIntervals, options = {}) {
    if (!Array.isArray(frameIntervals) || frameIntervals.length === 0) {
        return false;
    }

    const warmupFrames = options.warmupFrames ?? 2;
    const stableIntervals = frameIntervals.slice(Math.min(warmupFrames, frameIntervals.length));

    if (stableIntervals.length === 0) {
        return false;
    }

    const averageThreshold = options.averageThreshold ?? 115;
    const slowFrameThreshold = options.slowFrameThreshold ?? 150;
    const maxSlowFrames = options.maxSlowFrames ?? Math.ceil(stableIntervals.length / 2);
    const maxFrameThreshold = options.maxFrameThreshold ?? 250;
    const averageFrameInterval = stableIntervals.reduce((sum, value) => sum + value, 0) / stableIntervals.length;
    const slowFrames = stableIntervals.filter((value) => value >= slowFrameThreshold).length;
    const slowestFrame = Math.max(...stableIntervals);

    return averageFrameInterval >= averageThreshold || slowFrames >= maxSlowFrames || slowestFrame >= maxFrameThreshold;
}

export function createPreviewController(root, options = {}) {
    const requestButton = root.querySelector('[data-live-stream-preview-request]');
    const panelElement = root.querySelector('[data-live-stream-preview-panel]');
    const previewContentElement = root.querySelector('[data-live-stream-preview-content]');
    const videoElement = root.querySelector('[data-live-stream-preview]');
    const meterElement = root.querySelector('[data-live-stream-mic-meter]');
    const micLabelElement = root.querySelector('[data-live-stream-mic-label]');
    const statusElement = root.querySelector('[data-live-stream-device-status]');
    const emptyStateElement = root.querySelector('[data-live-stream-preview-empty]');
    const backgroundButton = root.querySelector('[data-live-stream-background-button]');
    const backgroundButtonLabel = root.querySelector('[data-live-stream-background-button-label]');
    const backgroundModal = root.querySelector('[data-live-stream-background-modal]');
    const backgroundWarningElement = root.querySelector('[data-live-stream-background-warning]');
    const backgroundOptionButtons = [...root.querySelectorAll('[data-live-stream-background-option]')];
    const backgroundImageOptionsContainer = root.querySelector('[data-live-stream-background-image-options]');
    const backgroundImageOptionsEmpty = root.querySelector('[data-live-stream-background-image-options-empty]');
    const backgroundUploadInput = root.querySelector('[data-live-stream-background-upload-input]');
    const cameraDeviceList = root.querySelector('[data-live-stream-camera-device-list]');
    const microphoneDeviceList = root.querySelector('[data-live-stream-microphone-device-list]');

    let mediaStream = null;
    let audioContext = null;
    let analyserNode = null;
    let animationFrameId = null;
    let microphoneSource = null;
    let localAudioTrack = null;
    let localVideoTrack = null;
    let activeBackgroundProcessor = null;
    let currentBackgroundMode = 'none';
    let currentBackgroundImageId = null;
    let applyBackgroundPromise = null;
    let backgroundFeatureDisabled = !isHardwareLikelySufficient();
    let backgroundBrowserSupported = null;
    const processorCache = new Map();
    const backgroundImageCache = new Map();
    let backgroundOptions = [];
    let backgroundOptionsPromise = null;
    let temporaryBackgroundOption = null;
    let cameraDevices = [];
    let microphoneDevices = [];
    let selectedCameraDeviceId = 'default';
    let selectedMicrophoneDeviceId = 'default';

    async function loadVideoProcessorsModule() {
        if (!videoProcessorsModulePromise) {
            videoProcessorsModulePromise = import('@twilio/video-processors');
        }

        return videoProcessorsModulePromise;
    }

    function hasVideoTrack() {
        return localVideoTrack !== null;
    }

    function notifyAudioStateChange() {
        if (typeof options.onAudioStateChange === 'function') {
            options.onAudioStateChange({
                hasAudioTrack: localAudioTrack !== null,
                audioEnabled: localAudioTrack?.isEnabled ?? false,
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

    function setBackgroundWarning(message = '') {
        if (!(backgroundWarningElement instanceof HTMLElement)) {
            return;
        }

        backgroundWarningElement.textContent = message;
        backgroundWarningElement.classList.toggle('hidden', message === '');
    }

    function setStatusMessage(message = '') {
        if (!(statusElement instanceof HTMLElement)) {
            return;
        }

        statusElement.textContent = message;
        statusElement.classList.toggle('hidden', message === '');
    }

    function updateBackgroundButtonVisibility() {
        if (!(backgroundButton instanceof HTMLButtonElement)) {
            return;
        }

        const canShowBackgroundButton = !backgroundFeatureDisabled && hasVideoTrack();

        backgroundButton.classList.toggle('hidden', !canShowBackgroundButton);
        backgroundButton.disabled = !canShowBackgroundButton || applyBackgroundPromise !== null;
    }

    async function ensureBackgroundProcessorsSupported() {
        if (backgroundFeatureDisabled) {
            return false;
        }

        if (backgroundBrowserSupported !== null) {
            return backgroundBrowserSupported;
        }

        try {
            const videoProcessorsModule = await loadVideoProcessorsModule();

            backgroundBrowserSupported = Boolean(videoProcessorsModule.isSupported);
        } catch (error) {
            console.error(error);
            backgroundBrowserSupported = false;
        }

        if (!backgroundBrowserSupported) {
            backgroundFeatureDisabled = true;
            updateBackgroundButtonVisibility();
        }

        return backgroundBrowserSupported;
    }

    function updateBackgroundButtonLabel() {
        if (!(backgroundButtonLabel instanceof HTMLElement)) {
            return;
        }

        backgroundButtonLabel.textContent = 'Sfondo';
    }

    function updateBackgroundOptionButtons() {
        backgroundOptionButtons.forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const buttonMode = button.dataset.liveStreamBackgroundOption ?? null;
            const buttonBackgroundId = button.dataset.liveStreamBackgroundId ?? null;
            const isSelected = buttonMode === currentBackgroundMode
                && (buttonMode !== 'image' || buttonBackgroundId === currentBackgroundImageId);

            button.classList.toggle('btn-primary', isSelected);
            button.classList.toggle('btn-outline', !isSelected);
            button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
    }

    function formatMediaInputDeviceLabel(device, index, fallback) {
        const label = device?.label?.trim();

        if (label) {
            return label;
        }

        return `${fallback} ${index + 1}`;
    }

    async function refreshInputDevices() {
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.enumerateDevices !== 'function') {
            cameraDevices = [];
            microphoneDevices = [];
            renderInputDevices();
            return;
        }

        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            cameraDevices = devices.filter((device) => device.kind === 'videoinput');
            microphoneDevices = devices.filter((device) => device.kind === 'audioinput');

            if (!cameraDevices.some((device) => device.deviceId === selectedCameraDeviceId)) {
                selectedCameraDeviceId = cameraDevices[0]?.deviceId ?? 'default';
            }

            if (!microphoneDevices.some((device) => device.deviceId === selectedMicrophoneDeviceId)) {
                selectedMicrophoneDeviceId = microphoneDevices[0]?.deviceId ?? 'default';
            }

            renderInputDevices();
        } catch (error) {
            cameraDevices = [];
            microphoneDevices = [];
            renderInputDevices();
            console.error(error);
        }
    }

    function renderInputDeviceSelect(selectElement, devices, selectedDeviceId, fallbackLabel) {
        if (!(selectElement instanceof HTMLSelectElement)) {
            return;
        }

        selectElement.replaceChildren();

        if (devices.length === 0) {
            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = `Nessun dispositivo ${fallbackLabel.toLowerCase()} disponibile.`;
            selectElement.appendChild(emptyOption);
            selectElement.disabled = true;

            return;
        }

        selectElement.disabled = false;

        devices.forEach((device, index) => {
            const option = document.createElement('option');
            option.value = device.deviceId;
            option.textContent = formatMediaInputDeviceLabel(device, index, fallbackLabel);
            option.selected = device.deviceId === selectedDeviceId;
            selectElement.appendChild(option);
        });
    }

    function renderInputDevices() {
        renderInputDeviceSelect(
            cameraDeviceList,
            cameraDevices,
            selectedCameraDeviceId,
            'Telecamera',
        );
        renderInputDeviceSelect(
            microphoneDeviceList,
            microphoneDevices,
            selectedMicrophoneDeviceId,
            'Microfono',
        );
    }

    async function selectCameraDevice(deviceId) {
        if (selectedCameraDeviceId === deviceId) {
            return;
        }

        selectedCameraDeviceId = deviceId;
        renderInputDevices();

        if (mediaStream) {
            await restartPreview();
        }
    }

    async function selectMicrophoneDevice(deviceId) {
        if (selectedMicrophoneDeviceId === deviceId) {
            return;
        }

        selectedMicrophoneDeviceId = deviceId;
        renderInputDevices();

        if (mediaStream) {
            await restartPreview();
        }
    }

    if (cameraDeviceList instanceof HTMLSelectElement) {
        cameraDeviceList.addEventListener('change', async (event) => {
            const deviceId = event.currentTarget.value;

            if (!deviceId) {
                return;
            }

            await selectCameraDevice(deviceId);
        });
    }

    if (microphoneDeviceList instanceof HTMLSelectElement) {
        microphoneDeviceList.addEventListener('change', async (event) => {
            const deviceId = event.currentTarget.value;

            if (!deviceId) {
                return;
            }

            await selectMicrophoneDevice(deviceId);
        });
    }

    function renderBackgroundImageOptions() {
        if (!(backgroundImageOptionsContainer instanceof HTMLElement)) {
            return;
        }

        for (let index = backgroundOptionButtons.length - 1; index >= 0; index -= 1) {
            if (backgroundOptionButtons[index]?.dataset.liveStreamBackgroundOption === 'image') {
                backgroundOptionButtons.splice(index, 1);
            }
        }

        backgroundImageOptionsContainer.replaceChildren();

        if (backgroundImageOptionsEmpty instanceof HTMLElement) {
            backgroundImageOptionsEmpty.classList.toggle('hidden', backgroundOptions.length > 0);
        }

        backgroundOptions.forEach((option) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline h-auto min-h-0 overflow-hidden p-0';
            button.dataset.liveStreamBackgroundOption = 'image';
            button.dataset.liveStreamBackgroundId = option.id;
            button.setAttribute('aria-pressed', 'false');

            const image = document.createElement('img');
            image.src = option.url;
            image.alt = option.label;
            image.className = 'aspect-video w-full object-cover';
            button.append(image);
            button.addEventListener('click', async () => {
                await applyBackgroundMode('image', option.id);
            });

            backgroundImageOptionsContainer.appendChild(button);
            backgroundOptionButtons.push(button);
        });

        updateBackgroundOptionButtons();
    }

    function revokeTemporaryBackgroundOption() {
        if (!temporaryBackgroundOption?.url?.startsWith('blob:')) {
            temporaryBackgroundOption = null;
            return;
        }

        backgroundImageCache.delete(temporaryBackgroundOption.url);
        URL.revokeObjectURL(temporaryBackgroundOption.url);
        temporaryBackgroundOption = null;
    }

    function upsertTemporaryBackgroundOption(file) {
        revokeTemporaryBackgroundOption();

        const objectUrl = URL.createObjectURL(file);

        temporaryBackgroundOption = {
            id: `temporary:${Date.now()}`,
            label: file.name || 'Immagine temporanea',
            url: objectUrl,
            temporary: true,
        };

        backgroundOptions = [
            temporaryBackgroundOption,
            ...backgroundOptions.filter((option) => !option.temporary),
        ];
        renderBackgroundImageOptions();

        return temporaryBackgroundOption;
    }

    function closeBackgroundModal() {
        if (!(backgroundModal instanceof HTMLDialogElement) || !backgroundModal.open) {
            return;
        }

        backgroundModal.close();
    }

    function showPreviewEmptyState() {
        if (videoElement instanceof HTMLVideoElement) {
            videoElement.srcObject = null;
            videoElement.classList.add('hidden');
        }

        if (emptyStateElement instanceof HTMLElement) {
            emptyStateElement.classList.remove('hidden');
            emptyStateElement.classList.add('flex');
        }
    }

    async function attachPreviewVideoTrack() {
        if (!(videoElement instanceof HTMLVideoElement) || !localVideoTrack) {
            showPreviewEmptyState();
            return;
        }

        localVideoTrack.attach(videoElement);
        videoElement.classList.remove('hidden');

        if (emptyStateElement instanceof HTMLElement) {
            emptyStateElement.classList.add('hidden');
            emptyStateElement.classList.remove('flex');
        }

        try {
            await videoElement.play();
        } catch (error) {
            console.error(error);
        }
    }

    function destroyLocalTracks() {
        if (activeBackgroundProcessor && localVideoTrack) {
            try {
                localVideoTrack.removeProcessor(activeBackgroundProcessor);
            } catch (error) {
                console.error(error);
            }
        }

        activeBackgroundProcessor = null;
        currentBackgroundMode = 'none';
        currentBackgroundImageId = null;
        revokeTemporaryBackgroundOption();
        backgroundOptions = backgroundOptions.filter((option) => !option.temporary);

        if (localAudioTrack) {
            localAudioTrack.stop();
            localAudioTrack = null;
        }

        if (localVideoTrack) {
            localVideoTrack.detach(videoElement instanceof HTMLVideoElement ? videoElement : undefined);
            localVideoTrack.stop();
            localVideoTrack = null;
        }
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
        closeBackgroundModal();
        setBackgroundWarning('');
        destroyLocalTracks();

        if (mediaStream) {
            mediaStream.getTracks().forEach((track) => {
                if (track.readyState !== 'ended') {
                    track.stop();
                }
            });
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
        updateBackgroundButtonVisibility();
        updateBackgroundButtonLabel();
        updateBackgroundOptionButtons();
    }

    async function restartPreview() {
        const backgroundMode = currentBackgroundMode;
        const backgroundImageId = currentBackgroundImageId;

        resetPreview();
        await openPreview();

        if (backgroundMode !== 'none') {
            await applyBackgroundMode(backgroundMode, backgroundImageId);
        }
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

    function getLocalTracks() {
        return [localAudioTrack, localVideoTrack].filter(Boolean);
    }

    function getSelectedDeviceConstraint(deviceId) {
        if (!deviceId || deviceId === 'default') {
            return true;
        }

        return {
            deviceId: {
                exact: deviceId,
            },
        };
    }

    function getVideoConstraints() {
        const deviceConstraint = getSelectedDeviceConstraint(selectedCameraDeviceId);

        if (deviceConstraint === true) {
            return LIVE_STREAM_VIDEO_CONSTRAINTS;
        }

        return {
            ...LIVE_STREAM_VIDEO_CONSTRAINTS,
            ...deviceConstraint,
        };
    }

    function createLocalTracks(stream) {
        const audioMediaTrack = stream.getAudioTracks()[0] ?? null;
        const videoMediaTrack = stream.getVideoTracks()[0] ?? null;

        localAudioTrack = audioMediaTrack ? new TwilioVideo.LocalAudioTrack(audioMediaTrack) : null;
        localVideoTrack = videoMediaTrack
            ? new TwilioVideo.LocalVideoTrack(videoMediaTrack, {
                name: options.videoTrackName,
            })
            : null;
    }

    async function ensureBackgroundImageLoaded(option) {
        if (!option?.url) {
            throw new Error('Background image option is missing a URL.');
        }

        if (backgroundImageCache.has(option.url)) {
            return backgroundImageCache.get(option.url);
        }

        const backgroundImagePromise = new Promise((resolve, reject) => {
            const image = new window.Image();
            image.decoding = 'async';
            image.onload = () => {
                resolve(image);
            };
            image.onerror = () => {
                backgroundImageCache.delete(option.url);
                reject(new Error(`Background image failed to load: ${option.url}`));
            };
            image.src = option.url;
        });

        backgroundImageCache.set(option.url, backgroundImagePromise);

        return backgroundImagePromise;
    }

    async function ensureBackgroundOptionsLoaded() {
        if (!options.backgroundsRoute) {
            backgroundOptions = [];
            renderBackgroundImageOptions();

            return backgroundOptions;
        }

        if (!backgroundOptionsPromise) {
            backgroundOptionsPromise = window.axios.get(options.backgroundsRoute)
                .then((response) => {
                    backgroundOptions = Array.isArray(response.data?.data) ? response.data.data : [];
                    renderBackgroundImageOptions();

                    return backgroundOptions;
                })
                .catch((error) => {
                    backgroundOptionsPromise = null;
                    throw error;
                });
        }

        return backgroundOptionsPromise;
    }

    async function getCachedBackgroundProcessor(mode, backgroundOption = null) {
        const videoProcessorsModule = await loadVideoProcessorsModule();
        const processorKey = mode === 'blur' ? 'blur' : 'image';
        let cachedProcessor = processorCache.get(processorKey);

        if (!cachedProcessor) {
            cachedProcessor = {
                processor: null,
                loadPromise: null,
            };

            processorCache.set(processorKey, cachedProcessor);
        }

        if (!cachedProcessor.processor) {
            if (processorKey === 'blur') {
                cachedProcessor.processor = new videoProcessorsModule.GaussianBlurBackgroundProcessor({
                    assetsPath: LIVE_STREAM_VIDEO_PROCESSOR_ASSETS_PATH,
                    blurFilterRadius: 15,
                });
            } else {
                const backgroundImage = await ensureBackgroundImageLoaded(backgroundOption);

                cachedProcessor.processor = new videoProcessorsModule.VirtualBackgroundProcessor({
                    assetsPath: LIVE_STREAM_VIDEO_PROCESSOR_ASSETS_PATH,
                    backgroundImage,
                });
            }
        }

        if (!cachedProcessor.loadPromise) {
            cachedProcessor.loadPromise = cachedProcessor.processor.loadModel().catch((error) => {
                processorCache.delete(processorKey);
                throw error;
            });
        }

        await cachedProcessor.loadPromise;

        if (processorKey === 'image' && backgroundOption) {
            cachedProcessor.processor.backgroundImage = await ensureBackgroundImageLoaded(backgroundOption);
        }

        return cachedProcessor.processor;
    }

    async function benchmarkBackgroundProcessor() {
        if (!(videoElement instanceof HTMLVideoElement) || typeof videoElement.requestVideoFrameCallback !== 'function') {
            return true;
        }

        const frameIntervals = [];

        return new Promise((resolve) => {
            let previousTimestamp = null;
            let settled = false;
            const timeoutId = window.setTimeout(() => {
                if (!settled) {
                    settled = true;
                    resolve(false);
                }
            }, 4000);

            const finish = (result) => {
                if (settled) {
                    return;
                }

                settled = true;
                window.clearTimeout(timeoutId);
                resolve(result);
            };

            const sampleFrame = (_, metadata) => {
                const timestamp = metadata.expectedDisplayTime ?? performance.now();

                if (previousTimestamp !== null) {
                    frameIntervals.push(timestamp - previousTimestamp);
                }

                previousTimestamp = timestamp;

                if (frameIntervals.length >= 12) {
                    finish(!isBackgroundProcessorBenchmarkSlow(frameIntervals));
                    return;
                }

                videoElement.requestVideoFrameCallback(sampleFrame);
            };

            videoElement.requestVideoFrameCallback(sampleFrame);
        });
    }

    async function removeActiveBackgroundProcessor() {
        if (!activeBackgroundProcessor || !localVideoTrack) {
            activeBackgroundProcessor = null;
            return;
        }

        localVideoTrack.removeProcessor(activeBackgroundProcessor);
        activeBackgroundProcessor = null;
    }

    async function applyBackgroundMode(mode, backgroundId = null) {
        if (applyBackgroundPromise || !localVideoTrack) {
            closeBackgroundModal();
            return;
        }

        applyBackgroundPromise = (async () => {
            try {
                if (mode !== 'none' && !await ensureBackgroundProcessorsSupported()) {
                    setBackgroundWarning('Sfondo virtuale non supportato da browser o dispositivo corrente.');
                    closeBackgroundModal();
                    return;
                }

                setBackgroundWarning('');
                await removeActiveBackgroundProcessor();
                currentBackgroundMode = 'none';
                currentBackgroundImageId = null;
                updateBackgroundButtonLabel();
                updateBackgroundOptionButtons();

                if (mode === 'none') {
                    closeBackgroundModal();
                    await attachPreviewVideoTrack();
                    return;
                }

                let backgroundOption = null;

                if (mode === 'image') {
                    await ensureBackgroundOptionsLoaded();
                    backgroundOption = backgroundOptions.find((option) => option.id === backgroundId) ?? null;

                    if (!backgroundOption) {
                        throw new Error('Selected background image is not available.');
                    }
                }

                const processor = await getCachedBackgroundProcessor(mode, backgroundOption);
                localVideoTrack.addProcessor(processor, LIVE_STREAM_VIDEO_PROCESSOR_ADD_OPTIONS);
                activeBackgroundProcessor = processor;
                currentBackgroundMode = mode;
                currentBackgroundImageId = backgroundOption?.id ?? null;
                updateBackgroundButtonLabel();
                updateBackgroundOptionButtons();
                closeBackgroundModal();
                await attachPreviewVideoTrack();

                const benchmarkPassed = await benchmarkBackgroundProcessor();

                if (!benchmarkPassed) {
                    backgroundFeatureDisabled = true;
                    await removeActiveBackgroundProcessor();
                    currentBackgroundMode = 'none';
                    currentBackgroundImageId = null;
                    updateBackgroundButtonLabel();
                    updateBackgroundOptionButtons();
                    updateBackgroundButtonVisibility();
                    setBackgroundWarning('Sfondo virtuale disattivato: dispositivo troppo lento per elaborare video in modo stabile.');
                    await attachPreviewVideoTrack();
                }
            } catch (error) {
                console.error(error);
                currentBackgroundMode = 'none';
                currentBackgroundImageId = null;
                updateBackgroundButtonLabel();
                updateBackgroundOptionButtons();
                setBackgroundWarning('Impossibile applicare sfondo virtuale. Riprova tra poco.');
                await removeActiveBackgroundProcessor();
                await attachPreviewVideoTrack();
            } finally {
                applyBackgroundPromise = null;
                updateBackgroundButtonVisibility();
            }
        })();

        await applyBackgroundPromise;
    }

    async function openPreview() {
        if (!(videoElement instanceof HTMLVideoElement)) {
            return;
        }

        if (mediaStream) {
            await attachPreviewVideoTrack();
            notifyAudioStateChange();
            updateBackgroundButtonVisibility();
            updateBackgroundButtonLabel();
            updateBackgroundOptionButtons();
            return;
        }

        try {
            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                throw new Error('getUserMedia is not available');
            }

            try {
                mediaStream = await navigator.mediaDevices.getUserMedia({
                    audio: getSelectedDeviceConstraint(selectedMicrophoneDeviceId),
                    video: getVideoConstraints(),
                });
            } catch (error) {
                if (!(error instanceof DOMException) || !['NotFoundError', 'OverconstrainedError'].includes(error.name)) {
                    throw error;
                }

                mediaStream = await navigator.mediaDevices.getUserMedia({
                    audio: getSelectedDeviceConstraint(selectedMicrophoneDeviceId),
                    video: false,
                });
            }

            createLocalTracks(mediaStream);
            const videoAvailable = hasVideoTrack();

            if (videoAvailable) {
                await attachPreviewVideoTrack();
            } else {
                showPreviewEmptyState();
            }

            setStatusMessage(
                videoAvailable
                    ? ''
                    : 'Microfono collegato. Nessuna videocamera disponibile: puoi comunque entrare nella diretta.',
            );

            startMicrophoneMeter(mediaStream);
            await refreshInputDevices();
            notifyAudioStateChange();
            updateRequestButton();
            updatePreviewContentVisibility();
            updateBackgroundButtonVisibility();
            updateBackgroundButtonLabel();
            updateBackgroundOptionButtons();

            if (videoAvailable) {
                void ensureBackgroundProcessorsSupported();
            }
        } catch (error) {
            setStatusMessage(
                error instanceof DOMException && error.name === 'NotFoundError'
                    ? 'Nessuna videocamera o nessun microfono disponibile su questo dispositivo.'
                    : 'Impossibile accedere a videocamera o microfono. Controlla i permessi del browser.',
            );

            console.error(error);
            notifyAudioStateChange();
            updateRequestButton();
            updatePreviewContentVisibility();
            updateBackgroundButtonVisibility();
            await refreshInputDevices();
        }
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

    if (backgroundButton instanceof HTMLButtonElement && backgroundModal instanceof HTMLDialogElement) {
        backgroundButton.addEventListener('click', async () => {
            if (backgroundButton.disabled) {
                return;
            }

            try {
                await ensureBackgroundOptionsLoaded();
            } catch (error) {
                console.error(error);
                setBackgroundWarning('Impossibile leggere cartella sfondi. Controlla file disponibili e riprova.');
            }

            backgroundModal.showModal();
        });
    }

    if (backgroundUploadInput instanceof HTMLInputElement) {
        backgroundUploadInput.addEventListener('change', async (event) => {
            const file = event.target.files?.[0] ?? null;

            if (!file) {
                return;
            }

            if (!file.type.startsWith('image/')) {
                setBackgroundWarning('File non valido. Carica un\'immagine supportata.');
                backgroundUploadInput.value = '';
                return;
            }

            try {
                const option = upsertTemporaryBackgroundOption(file);
                setBackgroundWarning('');
                backgroundUploadInput.value = '';
                await applyBackgroundMode('image', option.id);
            } catch (error) {
                console.error(error);
                setBackgroundWarning('Impossibile usare immagine caricata. Riprova con un file diverso.');
                backgroundUploadInput.value = '';
            }
        });
    }

    backgroundOptionButtons.forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.addEventListener('click', async () => {
            const mode = button.dataset.liveStreamBackgroundOption ?? 'none';
            const backgroundId = button.dataset.liveStreamBackgroundId ?? null;
            await applyBackgroundMode(mode, backgroundId);
        });
    });

    updateRequestButton();
    updatePreviewContentVisibility();
    updateBackgroundButtonVisibility();
    updateBackgroundButtonLabel();
    updateBackgroundOptionButtons();
    renderInputDevices();

    if (navigator.mediaDevices?.addEventListener) {
        navigator.mediaDevices.addEventListener('devicechange', () => {
            void refreshInputDevices();
        });
    }

    return {
        async open() {
            await openPreview();
        },
        hasAudioTrack() {
            return localAudioTrack !== null;
        },
        hasVideoTrack,
        isAudioEnabled() {
            return localAudioTrack?.isEnabled ?? false;
        },
        toggleAudio() {
            if (!localAudioTrack) {
                return false;
            }

            if (localAudioTrack.isEnabled) {
                localAudioTrack.disable();
            } else {
                localAudioTrack.enable();
            }

            notifyAudioStateChange();

            return localAudioTrack.isEnabled;
        },
        getLocalTracks() {
            return getLocalTracks();
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
