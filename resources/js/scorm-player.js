const root = document.querySelector('[data-scorm-player-root]');

if (root) {
    const configElement = root.querySelector('[data-scorm-player-config]');
    const iframe = root.querySelector('[data-scorm-player-iframe]');
    const statusElement = root.querySelector('[data-scorm-player-status]');

    if (configElement instanceof HTMLScriptElement && iframe instanceof HTMLIFrameElement) {
        const config = JSON.parse(configElement.textContent ?? '{}');
        const sessionId = window.crypto?.randomUUID?.() ?? `scorm-${Date.now()}`;
        const localState = {};
        let initialized = false;
        let terminated = false;
        let lastError = '0';
        let autosaveTimer = null;

        const updateStatus = (message) => {
            if (statusElement) {
                statusElement.textContent = message;
            }
        };

        const postRuntime = (url, payload = {}) => {
            const request = new XMLHttpRequest();
            request.open('POST', url, false);
            request.setRequestHeader('Accept', 'application/json');
            request.setRequestHeader('Content-Type', 'application/json');
            request.setRequestHeader('X-CSRF-TOKEN', config.csrfToken);
            request.withCredentials = true;
            request.send(JSON.stringify({
                session_id: sessionId,
                sco_identifier: config.defaultScoIdentifier,
                version: config.version,
                ...payload,
            }));

            if (request.status < 200 || request.status >= 300) {
                lastError = '101';
                throw new Error(`Runtime request failed with status ${request.status}`);
            }

            return JSON.parse(request.responseText || '{}');
        };

        const initialize = () => {
            if (initialized) {
                lastError = '0';

                return 'true';
            }

            try {
                const response = postRuntime(config.runtime.initialize);
                Object.assign(localState, response.state ?? {});
                initialized = true;
                lastError = response.error ?? '0';
                updateStatus('Runtime SCORM inizializzato');

                return 'true';
            } catch (error) {
                updateStatus('Errore durante l\'inizializzazione del runtime');

                return 'false';
            }
        };

        const getValue = (element) => {
            if (!initialized) {
                lastError = '301';

                return '';
            }

            if (Object.prototype.hasOwnProperty.call(localState, element)) {
                lastError = '0';

                return localState[element] ?? '';
            }

            try {
                const response = postRuntime(config.runtime.getValue, { element });
                const value = response.value ?? '';
                localState[element] = value;
                lastError = response.error ?? '0';

                return value;
            } catch (error) {
                lastError = '101';

                return '';
            }
        };

        const setValue = (element, value) => {
            if (!initialized) {
                lastError = '301';

                return 'false';
            }

            localState[element] = value;

            try {
                const response = postRuntime(config.runtime.setValue, { element, value });
                lastError = response.error ?? '0';

                return 'true';
            } catch (error) {
                lastError = '101';

                return 'false';
            }
        };

        const commit = () => {
            if (!initialized) {
                lastError = '301';

                return 'false';
            }

            try {
                const response = postRuntime(config.runtime.commit, { values: localState });
                Object.assign(localState, response.state ?? {});
                lastError = response.error ?? '0';
                updateStatus('Progresso salvato');

                return 'true';
            } catch (error) {
                lastError = '391';
                updateStatus('Errore durante il salvataggio del progresso');

                return 'false';
            }
        };

        const terminate = () => {
            if (terminated) {
                lastError = '0';

                return 'true';
            }

            try {
                const response = postRuntime(config.runtime.terminate, { values: localState });
                Object.assign(localState, response.state ?? {});
                terminated = true;
                lastError = response.error ?? '0';
                updateStatus('Sessione SCORM terminata');

                if (autosaveTimer !== null) {
                    window.clearInterval(autosaveTimer);
                }

                return 'true';
            } catch (error) {
                lastError = '101';

                return 'false';
            }
        };

        const getLastError = () => lastError;

        const getErrorString = (code) => {
            try {
                const response = postRuntime(config.runtime.getErrorString, { code });

                return response.value ?? '';
            } catch (error) {
                return '';
            }
        };

        const getDiagnostic = (code) => {
            try {
                const response = postRuntime(config.runtime.getDiagnostic, { code });

                return response.value ?? '';
            } catch (error) {
                return '';
            }
        };

        class Scorm12Api {
            LMSInitialize() {
                return initialize();
            }

            LMSGetValue(element) {
                return getValue(element);
            }

            LMSSetValue(element, value) {
                return setValue(element, value);
            }

            LMSCommit() {
                return commit();
            }

            LMSFinish() {
                return terminate();
            }

            LMSGetLastError() {
                return getLastError();
            }

            LMSGetErrorString(code) {
                return getErrorString(code);
            }

            LMSGetDiagnostic(code) {
                return getDiagnostic(code);
            }
        }

        class Scorm2004Api {
            Initialize() {
                return initialize();
            }

            GetValue(element) {
                return getValue(element);
            }

            SetValue(element, value) {
                return setValue(element, value);
            }

            Commit() {
                return commit();
            }

            Terminate() {
                return terminate();
            }

            GetLastError() {
                return getLastError();
            }

            GetErrorString(code) {
                return getErrorString(code);
            }

            GetDiagnostic(code) {
                return getDiagnostic(code);
            }
        }

        window.API = new Scorm12Api();
        window.API_1484_11 = new Scorm2004Api();

        autosaveTimer = window.setInterval(() => {
            commit();
        }, 30000);

        window.addEventListener('pagehide', () => {
            if (!terminated) {
                terminate();
            }
        });

        window.addEventListener('beforeunload', () => {
            if (!terminated) {
                commit();
            }
        });

        updateStatus('Player SCORM pronto');
    }
}
