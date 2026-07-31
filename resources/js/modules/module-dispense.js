import { fetchJSON, getModuleData, getModuleRoot } from './module-base.js';

export function initDispenseModule() {
    const root = getModuleRoot();
    const container = root?.querySelector('[data-dispense-module]');

    if (! root || ! container) {
        return;
    }

    const data = getModuleData(root);
    const waiting = container.querySelector('[data-dispense-waiting]');
    const proceed = container.querySelector('[data-dispense-proceed]');
    const timer = container.querySelector('[data-dispense-timer]');
    const error = container.querySelector('[data-dispense-error]');
    let countdown;

    const formatDuration = (seconds) => [
        Math.floor(seconds / 3600),
        Math.floor((seconds % 3600) / 60),
        seconds % 60,
    ].map((value) => String(value).padStart(2, '0')).join(':');

    const showState = (state) => {
        const downloadedIds = new Set(state.downloaded_ids.map(String));

        container.querySelectorAll('[data-dispense-material]').forEach((material) => {
            const downloaded = downloadedIds.has(material.dataset.dispenseMaterial);
            const badge = material.querySelector('[data-dispense-material-status]');
            badge.textContent = downloaded ? 'Scaricato' : 'Da scaricare';
            badge.classList.toggle('badge-success', downloaded);
            badge.classList.toggle('badge-ghost', ! downloaded);
        });

        window.clearInterval(countdown);
        waiting.classList.toggle('hidden', ! state.all_downloaded || state.can_proceed);
        proceed.classList.toggle('hidden', ! state.can_proceed);
        proceed.classList.toggle('flex', state.can_proceed);

        if (state.remaining_seconds > 0) {
            let remaining = state.remaining_seconds;
            timer.textContent = formatDuration(remaining);
            countdown = window.setInterval(() => {
                remaining = Math.max(0, remaining - 1);
                timer.textContent = formatDuration(remaining);

                if (remaining === 0) {
                    window.clearInterval(countdown);
                    refresh();
                }
            }, 1000);
        }
    };

    const refresh = () => fetchJSON(data.dispenseStatusUrl).then(showState).catch(() => {
        error.textContent = 'Impossibile aggiornare lo stato dei download.';
        error.classList.remove('hidden');
    });

    container.querySelectorAll('[data-dispense-download]').forEach((link) => {
        link.addEventListener('click', () => window.setTimeout(refresh, 1000));
    });

    container.querySelector('[data-dispense-complete]').addEventListener('click', async (event) => {
        event.currentTarget.disabled = true;
        error.classList.add('hidden');

        try {
            await fetchJSON(data.dispenseCompleteUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': data.csrfToken },
            });
            window.location.href = data.dispenseRedirectUrl;
        } catch {
            error.textContent = 'Non è ancora possibile proseguire.';
            error.classList.remove('hidden');
            event.currentTarget.disabled = false;
            refresh();
        }
    });

    refresh();
}
