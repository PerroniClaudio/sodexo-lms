function rememberButtonState(button) {
    if (button.dataset.loadingOriginalContent !== undefined) {
        return;
    }

    if (button instanceof HTMLInputElement) {
        button.dataset.loadingOriginalContent = button.value;
    } else {
        button.dataset.loadingOriginalContent = button.innerHTML;
    }

    button.dataset.loadingOriginalDisabled = button.disabled ? 'true' : 'false';
}

export function setButtonLoading(button, loading, options = {}) {
    if (!(button instanceof HTMLElement)) {
        return;
    }

    const loadingText = options.loadingText || button.dataset.loadingText || button.textContent?.trim() || 'Caricamento...';
    const spinnerClass = options.spinnerClass || button.dataset.loadingSpinnerClass || 'loading loading-spinner loading-sm';

    rememberButtonState(button);

    if (loading) {
        button.dataset.loadingActive = 'true';
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;

        if (button instanceof HTMLInputElement) {
            button.value = loadingText;

            return;
        }

        const spinner = document.createElement('span');
        spinner.className = spinnerClass;

        const label = document.createElement('span');
        label.textContent = loadingText;

        button.replaceChildren(spinner, label);

        if (!button.classList.contains('gap-2')) {
            button.dataset.loadingAddedGap = 'true';
            button.classList.add('gap-2');
        }

        return;
    }

    button.removeAttribute('aria-busy');
    delete button.dataset.loadingActive;

    const wasDisabled = button.dataset.loadingOriginalDisabled === 'true';
    button.disabled = wasDisabled;

    if (button instanceof HTMLInputElement) {
        button.value = button.dataset.loadingOriginalContent || '';
    } else if (button.dataset.loadingOriginalContent !== undefined) {
        button.innerHTML = button.dataset.loadingOriginalContent;
    }

    if (button.dataset.loadingAddedGap === 'true') {
        button.classList.remove('gap-2');
        delete button.dataset.loadingAddedGap;
    }
}

export function toggleAsyncTableLoading({ scope = null, container = null, loader = null }, loading) {
    [scope, container].forEach((element) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        element.classList.toggle('pointer-events-none', loading);
        element.classList.toggle('opacity-70', loading);
    });

    if (loader instanceof HTMLElement) {
        loader.classList.toggle('hidden', !loading);
        loader.classList.toggle('flex', loading);
    }
}

export function bindModalSubmitLoading(root = document) {
    if (!(root instanceof Document) || root.body.dataset.modalSubmitLoadingBound === 'true') {
        return;
    }

    root.body.dataset.modalSubmitLoadingBound = 'true';

    root.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.getAttribute('method')?.toLowerCase() === 'dialog') {
            return;
        }

        if (!form.closest('dialog.modal')) {
            return;
        }

        const submitter = event.submitter instanceof HTMLElement
            ? event.submitter
            : form.querySelector('[data-modal-submit-loading]');

        if (!(submitter instanceof HTMLElement) || !submitter.hasAttribute('data-modal-submit-loading')) {
            return;
        }

        setButtonLoading(submitter, true);

        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((element) => {
            if (element !== submitter) {
                element.disabled = true;
            }
        });
    }, true);
}