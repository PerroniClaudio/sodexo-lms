document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-tutor-attendance-page]');

    if (!page) {
        return;
    }

    initializeTutorAttendanceQr(page);
});

function initializeTutorAttendanceQr(page) {
    const openButton = page.querySelector('[data-open-qr-scan-modal]');
    const dialog = document.querySelector('[data-qr-scan-modal]');
    const closeButtons = Array.from(document.querySelectorAll('[data-close-qr-scan-modal]'));
    const video = dialog?.querySelector('[data-qr-scan-video]');
    const statusElement = dialog?.querySelector('[data-qr-scan-status]');
    const scanUrl = page.dataset.qrScanUrl;
    const csrfToken = page.dataset.csrfToken;

    if (!(openButton instanceof HTMLButtonElement) || !(dialog instanceof HTMLDialogElement) || !(video instanceof HTMLVideoElement) || !(statusElement instanceof HTMLElement) || !scanUrl || !csrfToken) {
        return;
    }

    let mediaStream = null;
    let scanIntervalId = null;
    let detector = null;
    let isSubmitting = false;

    const setStatus = (message, tone = 'neutral') => {
        statusElement.textContent = message;
        statusElement.classList.remove('border-error', 'bg-error/10', 'text-error', 'border-success', 'bg-success/10', 'text-success-content');

        if (tone === 'error') {
            statusElement.classList.add('border-error', 'bg-error/10', 'text-error');
        }

        if (tone === 'success') {
            statusElement.classList.add('border-success', 'bg-success/10', 'text-success-content');
        }
    };

    const stopScanner = () => {
        if (scanIntervalId !== null) {
            window.clearInterval(scanIntervalId);
            scanIntervalId = null;
        }

        if (mediaStream instanceof MediaStream) {
            mediaStream.getTracks().forEach((track) => track.stop());
            mediaStream = null;
        }

        video.pause();
        video.srcObject = null;
        isSubmitting = false;
    };

    const closeDialog = () => {
        stopScanner();

        if (dialog.open) {
            dialog.close();
        }
    };

    const showFlashMessage = (type, message) => {
        if (typeof window.showFlash === 'function') {
            window.showFlash(type, message);

            return;
        }

        setStatus(message, type === 'error' ? 'error' : 'success');
    };

    const submitQrContent = async (qrContent) => {
        const response = await fetch(scanUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                qr_content: qrContent,
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'Scansione non valida.');
        }

        return payload;
    };

    const startScanner = async () => {
        if (typeof window.BarcodeDetector !== 'function') {
            throw new Error('Questo browser non supporta la scansione QR integrata.');
        }

        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            throw new Error('La telecamera del dispositivo non è disponibile.');
        }

        detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        mediaStream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
            },
        });

        video.srcObject = mediaStream;
        await video.play();

        setStatus('Inquadra il QR code dell\'utente.', 'neutral');

        scanIntervalId = window.setInterval(async () => {
            if (isSubmitting || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
                return;
            }

            try {
                const barcodes = await detector.detect(video);
                const qrCode = barcodes.find((barcode) => typeof barcode.rawValue === 'string' && barcode.rawValue.trim() !== '');

                if (!qrCode) {
                    return;
                }

                isSubmitting = true;
                setStatus('Verifica iscrizione e registrazione presenza in corso...', 'neutral');

                const payload = await submitQrContent(qrCode.rawValue.trim());

                closeDialog();
                showFlashMessage('success', payload.message || 'Presenza registrata con successo.');
            } catch (error) {
                isSubmitting = false;

                setStatus(error instanceof Error ? error.message : 'Scansione non valida.', 'error');
            }
        }, 500);
    };

    openButton.addEventListener('click', async () => {
        dialog.showModal();
        setStatus('Apertura telecamera in corso...', 'neutral');

        try {
            await startScanner();
        } catch (error) {
            stopScanner();
            setStatus(error instanceof Error ? error.message : 'Impossibile avviare la telecamera.', 'error');
        }
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeDialog);
    });

    dialog.addEventListener('close', stopScanner);
}