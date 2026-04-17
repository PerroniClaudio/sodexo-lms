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

function renderLiveStreamTeacherChat() {
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

function stopMediaPreview(videoElement, meterElement, statusElement, emptyStateElement) {
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

    if (statusElement instanceof HTMLElement) {
        statusElement.textContent = 'Consenti l’accesso a videocamera e microfono per visualizzare l’anteprima.';
    }
}

function startMicrophoneMeter(stream, meterElement) {
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
        meterElement.value = Math.min(100, Math.round(rms * 220));
        animationFrameId = requestAnimationFrame(updateMeter);
    };

    updateMeter();
}

async function openDevicePreview(videoElement, meterElement, statusElement, emptyStateElement) {
    if (!(videoElement instanceof HTMLVideoElement)) {
        return;
    }

    stopMediaPreview(videoElement, meterElement, statusElement, emptyStateElement);

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
                : '';
        }

        startMicrophoneMeter(mediaStream, meterElement);
    } catch (error) {
        if (statusElement instanceof HTMLElement) {
            statusElement.textContent =
                error instanceof DOMException && error.name === 'NotFoundError'
                    ? 'Nessuna videocamera o nessun microfono disponibile su questo dispositivo.'
                    : 'Impossibile accedere a videocamera o microfono. Controlla i permessi del browser.';
        }
    }
}

function setupTeacherDevicePreview() {
    const openButton = document.querySelector('[data-livestream-user-settings-open]');
    const panelElement = document.querySelector('[data-livestream-user-settings-panel]');
    const videoElement = document.querySelector('[data-livestream-user-preview]');
    const meterElement = document.querySelector('[data-livestream-user-mic-meter]');
    const statusElement = document.querySelector('[data-livestream-user-device-status]');
    const emptyStateElement = document.querySelector('[data-livestream-user-preview-empty]');

    if (!(openButton instanceof HTMLButtonElement) || !(panelElement instanceof HTMLElement)) {
        return;
    }

    openButton.addEventListener('click', async () => {
        const isOpen = !panelElement.classList.contains('hidden');

        if (isOpen) {
            panelElement.classList.add('hidden');
            stopMediaPreview(videoElement, meterElement, statusElement, emptyStateElement);
            return;
        }

        panelElement.classList.remove('hidden');
        await openDevicePreview(videoElement, meterElement, statusElement, emptyStateElement);
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

function setupTeacherMicToggles() {
    const toggleButtons = document.querySelectorAll('[data-livestream-teacher-mic-toggle]');

    toggleButtons.forEach((toggleButton) => {
        if (!(toggleButton instanceof HTMLButtonElement)) {
            return;
        }

        const micOffIcon = toggleButton.querySelector('[data-livestream-teacher-mic-off]');
        const micOnIcon = toggleButton.querySelector('[data-livestream-teacher-mic-on]');

        if (!(micOffIcon instanceof Element) || !(micOnIcon instanceof Element)) {
            return;
        }

        toggleButton.dataset.muted = 'true';

        toggleButton.addEventListener('click', () => {
            const isMuted = toggleButton.dataset.muted !== 'false';

            toggleButton.dataset.muted = isMuted ? 'false' : 'true';
            micOffIcon.classList.toggle('hidden', isMuted);
            micOnIcon.classList.toggle('hidden', !isMuted);
            toggleButton.classList.toggle('btn-primary', !isMuted);
            toggleButton.classList.toggle('btn-accent', isMuted);
            toggleButton.setAttribute(
                'aria-label',
                isMuted ? 'Disattiva microfono discente' : 'Attiva microfono discente',
            );
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        renderLiveStreamTeacherChat();
        setupTeacherDevicePreview();
        setupDetailsScroll();
        setupTeacherMicToggles();
    });
} else {
    renderLiveStreamTeacherChat();
    setupTeacherDevicePreview();
    setupDetailsScroll();
    setupTeacherMicToggles();
}
