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
    {
        author: 'Marco Bianchi',
        role: 'student',
        time: '10:03',
        body: 'Le slide saranno disponibili anche tra gli allegati?',
    },
];

let mediaStream = null;
let audioContext = null;
let analyserNode = null;
let animationFrameId = null;
let microphoneSource = null;

function getInitials(name) {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function formatDisplayName(name) {
    const parts = name
        .split(' ')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase());

    if (parts.length < 2) {
        return parts[0] ?? '';
    }

    return `${parts[0]} ${parts[1].charAt(0)}.`;
}

function renderLiveStreamUserChat() {
    const messagesContainer = document.querySelector('[data-livestream-user-chat-messages]');
    const messageTemplate = document.querySelector('[data-livestream-user-chat-template]');

    if (!(messagesContainer instanceof HTMLElement) || !(messageTemplate instanceof HTMLTemplateElement)) {
        return;
    }

    messagesContainer.replaceChildren();

    fakeMessages.forEach((message) => {
        const messageFragment = messageTemplate.content.cloneNode(true);

        if (!(messageFragment instanceof DocumentFragment)) {
            return;
        }

        const authorElement = messageFragment.querySelector('[data-chat-author]');
        const timeElement = messageFragment.querySelector('[data-chat-time]');
        const bodyElement = messageFragment.querySelector('[data-chat-body]');
        const initialsElement = messageFragment.querySelector('[data-chat-initials]');
        const bubbleElement = messageFragment.querySelector('[data-chat-bubble]');

        if (
            !(authorElement instanceof HTMLElement) ||
            !(timeElement instanceof HTMLElement) ||
            !(bodyElement instanceof HTMLElement) ||
            !(initialsElement instanceof HTMLElement) ||
            !(bubbleElement instanceof HTMLElement)
        ) {
            return;
        }

        authorElement.textContent = formatDisplayName(message.author);
        timeElement.textContent = message.time;
        bodyElement.textContent = message.body;
        initialsElement.textContent = getInitials(message.author);

        if (message.role === 'teacher' || message.role === 'tutor') {
            bubbleElement.classList.remove('bg-base-100', 'text-base-content');
            bubbleElement.classList.add('bg-primary', 'text-primary-content');
        }

        messagesContainer.appendChild(messageFragment);
    });
}

function stopMediaPreview(videoElement, meterElement, micLabelElement, statusElement, emptyStateElement) {
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
        micLabelElement.textContent = 'In attesa del microfono';
    }

    if (statusElement instanceof HTMLElement) {
        statusElement.textContent = 'Consenti l’accesso a videocamera e microfono per visualizzare l’anteprima.';
    }
}

function startMicrophoneMeter(stream, meterElement, micLabelElement) {
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
        if (micLabelElement instanceof HTMLElement) {
            micLabelElement.textContent = level > 8 ? 'Microfono attivo' : 'Microfono in ascolto';
        }
        animationFrameId = requestAnimationFrame(updateMeter);
    };

    updateMeter();
}

async function openDevicePreview(videoElement, meterElement, micLabelElement, statusElement, emptyStateElement) {
    if (!(videoElement instanceof HTMLVideoElement)) {
        return;
    }

    stopMediaPreview(videoElement, meterElement, micLabelElement, statusElement, emptyStateElement);

    try {
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            throw new Error('getUserMedia is not available');
        }

        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: true,
            });
        } catch (error) {
            if (!(error instanceof DOMException) || error.name !== 'NotFoundError') {
                throw error;
            }

            mediaStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: false,
            });
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
                : '';
        }

        startMicrophoneMeter(mediaStream, meterElement, micLabelElement);
    } catch (error) {
        if (statusElement instanceof HTMLElement) {
            statusElement.textContent =
                error instanceof DOMException && error.name === 'NotFoundError'
                    ? 'Nessuna videocamera o nessun microfono disponibile su questo dispositivo.'
                    : 'Impossibile accedere a videocamera o microfono. Controlla i permessi del browser.';
        }

        if (micLabelElement instanceof HTMLElement) {
            micLabelElement.textContent =
                error instanceof DOMException && error.name === 'NotFoundError'
                    ? 'Nessun dispositivo rilevato'
                    : 'Microfono non disponibile';
        }

        console.error(error);
    }
}

function setupUserDevicePreview() {
    const openButton = document.querySelector('[data-livestream-user-settings-open]');
    const panelElement = document.querySelector('[data-livestream-user-settings-panel]');
    const videoElement = document.querySelector('[data-livestream-user-preview]');
    const meterElement = document.querySelector('[data-livestream-user-mic-meter]');
    const micLabelElement = document.querySelector('[data-livestream-user-mic-label]');
    const statusElement = document.querySelector('[data-livestream-user-device-status]');
    const emptyStateElement = document.querySelector('[data-livestream-user-preview-empty]');

    if (!(openButton instanceof HTMLButtonElement) || !(panelElement instanceof HTMLElement)) {
        return;
    }

    openButton.addEventListener('click', async () => {
        const isOpen = !panelElement.classList.contains('hidden');

        if (isOpen) {
            panelElement.classList.add('hidden');
            stopMediaPreview(videoElement, meterElement, micLabelElement, statusElement, emptyStateElement);
            return;
        }

        panelElement.classList.remove('hidden');
        await openDevicePreview(videoElement, meterElement, micLabelElement, statusElement, emptyStateElement);
    });
}

function setupDetailsScroll() {
    const scrollButton = document.querySelector('[data-livestream-user-scroll-details]');
    const scrollContainer = document.querySelector('[data-livestream-user-main-scroll]');
    const detailsSection = document.querySelector('[data-livestream-user-details-section]');

    if (
        !(scrollButton instanceof HTMLButtonElement) ||
        !(scrollContainer instanceof HTMLElement) ||
        !(detailsSection instanceof HTMLElement)
    ) {
        return;
    }

    scrollButton.addEventListener('click', () => {
        scrollContainer.scrollTo({
            top: detailsSection.offsetTop,
            behavior: 'smooth',
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        renderLiveStreamUserChat();
        setupUserDevicePreview();
        setupDetailsScroll();
    });
} else {
    renderLiveStreamUserChat();
    setupUserDevicePreview();
    setupDetailsScroll();
}
