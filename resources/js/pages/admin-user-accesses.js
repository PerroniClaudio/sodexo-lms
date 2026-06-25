document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-user-access-page]');

    if (!(page instanceof HTMLElement)) {
        return;
    }

    const scopeInputs = page.querySelectorAll('[data-user-access-scope]');
    const userFields = page.querySelector('[data-user-access-user-fields]');
    const jobFields = page.querySelector('[data-user-access-job-fields]');
    const jobDimensionSelect = page.querySelector('[data-user-access-job-dimension]');
    const jobValueSelect = page.querySelector('[data-user-access-job-value]');
    const jobValuesScript = page.querySelector('[data-user-access-job-values]');

    if (!(userFields instanceof HTMLElement) || !(jobFields instanceof HTMLElement) || !(jobDimensionSelect instanceof HTMLSelectElement) || !(jobValueSelect instanceof HTMLSelectElement) || !(jobValuesScript instanceof HTMLScriptElement)) {
        return;
    }

    const jobValues = JSON.parse(jobValuesScript.textContent || '{}');

    const renderJobValues = (selectedValue = '') => {
        const options = jobValues[jobDimensionSelect.value] || [];

        jobValueSelect.innerHTML = '<option value="">Seleziona valore</option>';

        options.forEach((option) => {
            const element = document.createElement('option');
            element.value = String(option.id);
            element.textContent = option.name;

            if (String(selectedValue) === String(option.id)) {
                element.selected = true;
            }

            jobValueSelect.appendChild(element);
        });
    };

    const syncScope = () => {
        const selectedScope = [...scopeInputs].find((input) => input instanceof HTMLInputElement && input.checked)?.value;
        const isUserScope = selectedScope === 'user';

        userFields.classList.toggle('hidden', !isUserScope);
        jobFields.classList.toggle('hidden', isUserScope);
    };

    scopeInputs.forEach((input) => {
        input.addEventListener('change', syncScope);
    });

    jobDimensionSelect.addEventListener('change', () => renderJobValues());

    renderJobValues(jobValueSelect.dataset.oldValue || '');
    syncScope();

    let pollingHandle = null;

    const pollRows = async () => {
        const rows = [...page.querySelectorAll('[data-user-access-export-row]')]
            .filter((row) => row instanceof HTMLElement && row.dataset.terminal !== 'true');

        if (rows.length === 0) {
            if (pollingHandle !== null) {
                window.clearInterval(pollingHandle);
            }

            pollingHandle = null;

            return;
        }

        await Promise.all(rows.map(async (row) => {
            const response = await window.axios.get(row.dataset.statusUrl, { headers: { Accept: 'application/json' } });
            const payload = response.data;
            const statusBadge = row.querySelector('[data-user-access-export-status-badge]');
            const outcome = row.querySelector('[data-user-access-export-outcome]');
            const downloadLink = row.querySelector('[data-user-access-export-download]');

            if (statusBadge instanceof HTMLElement) {
                statusBadge.className = `badge ${payload.status_badge_class}`;
                statusBadge.textContent = payload.status_label;
            }

            if (outcome instanceof HTMLElement) {
                if (payload.status === 'failed' && payload.error_message) {
                    outcome.innerHTML = `<span class="text-sm text-error">${payload.error_message}</span>`;
                } else if (payload.status === 'completed') {
                    outcome.innerHTML = '<span class="text-sm text-success">File pronto</span>';
                } else {
                    outcome.innerHTML = '<span class="text-sm text-base-content/60">In attesa completamento</span>';
                }
            }

            if (downloadLink instanceof HTMLAnchorElement && payload.download_url) {
                downloadLink.href = payload.download_url;
                downloadLink.classList.remove('hidden');
            }

            if (payload.is_terminal) {
                row.dataset.terminal = 'true';
            }
        }));
    };

    pollingHandle = window.setInterval(() => {
        void pollRows();
    }, 5000);
});
