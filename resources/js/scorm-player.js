const root = document.querySelector('[data-scorm-player-root]');

if (root) {
    const configElement = root.querySelector('[data-scorm-player-config]');
    const iframe = root.querySelector('[data-scorm-player-iframe]');
    const statusElement = root.querySelector('[data-scorm-player-status]');

    if (configElement instanceof HTMLScriptElement && iframe instanceof HTMLIFrameElement) {
        const config = JSON.parse(configElement.textContent ?? '{}');
        const sessionId = window.crypto?.randomUUID?.() ?? `scorm-${Date.now()}`;
        const localState = {};
        const dirtyState = {};
        const normalizedVersion = normalizeVersion(config.version);
        let initialized = false;
        let terminated = false;
        let lastError = '0';
        let autosaveTimer = null;
        let flushTimer = null;
        let flushInFlight = false;

        const updateStatus = (message) => {
            if (statusElement) {
                statusElement.textContent = message;
            }
        };

        const buildRuntimePayload = (payload = {}) => ({
            session_id: sessionId,
            sco_identifier: config.defaultScoIdentifier,
            version: normalizedVersion,
            ...payload,
        });

        const postRuntimeSync = (url, payload = {}) => {
            const request = new XMLHttpRequest();
            request.open('POST', url, false);
            request.setRequestHeader('Accept', 'application/json');
            request.setRequestHeader('Content-Type', 'application/json');
            request.setRequestHeader('X-CSRF-TOKEN', config.csrfToken);
            request.withCredentials = true;
            request.send(JSON.stringify(buildRuntimePayload(payload)));

            if (request.status < 200 || request.status >= 300) {
                lastError = '101';
                throw new Error(`Runtime request failed with status ${request.status}`);
            }

            return JSON.parse(request.responseText || '{}');
        };

        const postRuntimeAsync = async (url, payload = {}) => {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: JSON.stringify(buildRuntimePayload(payload)),
            });

            if (!response.ok) {
                throw new Error(`Runtime request failed with status ${response.status}`);
            }

            return response.json();
        };

        const getDirtyValues = () => ({ ...dirtyState });

        const clearDirtyValues = (values) => {
            Object.keys(values).forEach((key) => {
                delete dirtyState[key];
            });
        };

        const mergeState = (state) => {
            Object.assign(localState, state ?? {});
        };

        const scheduleAsyncFlush = () => {
            if (flushTimer !== null || flushInFlight || !initialized || terminated || Object.keys(dirtyState).length === 0) {
                return;
            }

            flushTimer = window.setTimeout(async () => {
                flushTimer = null;

                if (flushInFlight || terminated) {
                    return;
                }

                const values = getDirtyValues();

                if (Object.keys(values).length === 0) {
                    return;
                }

                flushInFlight = true;

                try {
                    const response = await postRuntimeAsync(config.runtime.commit, { values });
                    mergeState(response.state ?? {});
                    clearDirtyValues(values);
                    lastError = response.error ?? '0';
                    updateStatus('Progresso SCORM salvato');
                } catch (error) {
                    console.warn('[scorm] Flush runtime fallito:', error);
                } finally {
                    flushInFlight = false;

                    if (Object.keys(dirtyState).length > 0) {
                        scheduleAsyncFlush();
                    }
                }
            }, 1200);
        };

        const initialize = () => {
            if (initialized) {
                lastError = '0';

                return 'true';
            }

            try {
                const response = postRuntimeSync(config.runtime.initialize);
                mergeState(response.state ?? {});
                initialized = true;
                lastError = response.error ?? '0';
                updateStatus('Runtime SCORM inizializzato');

                return 'true';
            } catch (error) {
                console.error('[scorm] Inizializzazione runtime fallita:', error);
                updateStatus('Errore durante inizializzazione runtime SCORM');

                return 'false';
            }
        };

        const ensureInitialized = () => {
            if (initialized) {
                return true;
            }

            return initialize() === 'true';
        };

        const getValue = (element) => {
            if (!ensureInitialized()) {
                lastError = '301';

                return '';
            }

            if (Object.prototype.hasOwnProperty.call(localState, element)) {
                lastError = '0';

                return localState[element] ?? '';
            }

            try {
                const response = postRuntimeSync(config.runtime.getValue, { element });
                const value = response.value ?? '';
                localState[element] = value;
                lastError = response.error ?? '0';

                return value;
            } catch (error) {
                console.error('[scorm] Lettura runtime fallita:', error);
                lastError = '101';

                return '';
            }
        };

        const setValue = (element, value) => {
            if (!ensureInitialized()) {
                lastError = '301';

                return 'false';
            }

            localState[element] = value;
            dirtyState[element] = value;
            lastError = '0';
            scheduleAsyncFlush();

            return 'true';
        };

        const commit = () => {
            if (!ensureInitialized()) {
                lastError = '301';

                return 'false';
            }

            try {
                const values = Object.keys(dirtyState).length > 0 ? getDirtyValues() : { ...localState };
                const response = postRuntimeSync(config.runtime.commit, { values });
                mergeState(response.state ?? {});
                clearDirtyValues(values);
                lastError = response.error ?? '0';
                updateStatus('Progresso SCORM salvato');

                return 'true';
            } catch (error) {
                console.error('[scorm] Commit runtime fallito:', error);
                lastError = '391';
                updateStatus('Errore durante salvataggio progresso SCORM');

                return 'false';
            }
        };

        const terminate = () => {
            if (terminated) {
                lastError = '0';

                return 'true';
            }

            try {
                const values = Object.keys(dirtyState).length > 0 ? getDirtyValues() : { ...localState };
                const response = postRuntimeSync(config.runtime.terminate, { values });
                mergeState(response.state ?? {});
                clearDirtyValues(values);
                terminated = true;
                lastError = response.error ?? '0';
                updateStatus('Sessione SCORM terminata');

                if (autosaveTimer !== null) {
                    window.clearInterval(autosaveTimer);
                }

                if (flushTimer !== null) {
                    window.clearTimeout(flushTimer);
                }

                if (response.navigation?.url) {
                    window.location.assign(response.navigation.url);
                }

                return 'true';
            } catch (error) {
                console.error('[scorm] Terminazione runtime fallita:', error);
                lastError = '101';

                return 'false';
            }
        };

        const getLastError = () => lastError;

        const getErrorString = (code) => {
            try {
                const response = postRuntimeSync(config.runtime.getErrorString, { code });

                return response.value ?? '';
            } catch (error) {
                return '';
            }
        };

        const getDiagnostic = (code) => {
            try {
                const response = postRuntimeSync(config.runtime.getDiagnostic, { code });

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

        const scorm12Api = new Scorm12Api();
        const scorm2004Api = new Scorm2004Api();

        const installBridgeOnWindow = (targetWindow) => {
            if (!targetWindow) {
                return;
            }

            targetWindow.API = scorm12Api;
            targetWindow.API_1484_11 = scorm2004Api;
            targetWindow.LMSInitialize = (...args) => scorm12Api.LMSInitialize(...args);
            targetWindow.LMSGetValue = (...args) => scorm12Api.LMSGetValue(...args);
            targetWindow.LMSSetValue = (...args) => scorm12Api.LMSSetValue(...args);
            targetWindow.LMSCommit = (...args) => scorm12Api.LMSCommit(...args);
            targetWindow.LMSFinish = (...args) => scorm12Api.LMSFinish(...args);
            targetWindow.LMSGetLastError = (...args) => scorm12Api.LMSGetLastError(...args);
            targetWindow.LMSGetErrorString = (...args) => scorm12Api.LMSGetErrorString(...args);
            targetWindow.LMSGetDiagnostic = (...args) => scorm12Api.LMSGetDiagnostic(...args);
            targetWindow.Initialize = (...args) => scorm2004Api.Initialize(...args);
            targetWindow.GetValue = (...args) => scorm2004Api.GetValue(...args);
            targetWindow.SetValue = (...args) => scorm2004Api.SetValue(...args);
            targetWindow.Commit = (...args) => scorm2004Api.Commit(...args);
            targetWindow.Terminate = (...args) => scorm2004Api.Terminate(...args);
            targetWindow.GetLastError = (...args) => scorm2004Api.GetLastError(...args);
            targetWindow.GetErrorString = (...args) => scorm2004Api.GetErrorString(...args);
            targetWindow.GetDiagnostic = (...args) => scorm2004Api.GetDiagnostic(...args);
            targetWindow.ScormProcessInitialize = (...args) => initialize(...args);
            targetWindow.ScormProcessGetValue = (...args) => getValue(...args);
            targetWindow.ScormProcessSetValue = (...args) => setValue(...args);
            targetWindow.ScormProcessCommit = (...args) => commit(...args);
            targetWindow.ScormProcessFinish = (...args) => terminate(...args);
            targetWindow.ScormProcessTerminate = (...args) => terminate(...args);
            targetWindow.ScormProcessGetLastError = (...args) => getLastError(...args);
            targetWindow.ScormProcessGetErrorString = (...args) => getErrorString(...args);
            targetWindow.ScormProcessGetDiagnostic = (...args) => getDiagnostic(...args);
            targetWindow.doInitialize = (...args) => initialize(...args);
            targetWindow.doGetValue = (...args) => getValue(...args);
            targetWindow.doSetValue = (...args) => setValue(...args);
            targetWindow.doCommit = (...args) => commit(...args);
            targetWindow.doTerminate = (...args) => terminate(...args);
            targetWindow.GetAPI = () => targetWindow.API;
            targetWindow.GetAPI_1484_11 = () => targetWindow.API_1484_11;
            targetWindow.AddLicenseInfo = (...args) => (
                targetWindow.parent
                && targetWindow.parent !== targetWindow
                && typeof targetWindow.parent.AddLicenseInfo === 'function'
                    ? targetWindow.parent.AddLicenseInfo(...args)
                    : true
            );
        };

        installBridgeOnWindow(window);

        iframe.addEventListener('load', () => {
            try {
                installBridgeOnWindow(iframe.contentWindow);
            } catch (error) {
                console.warn('[scorm] Bridge iframe non applicato:', error);
            }
        });

        autosaveTimer = window.setInterval(() => {
            if (!terminated && initialized) {
                commit();
            }
        }, 30000);

        window.addEventListener('pagehide', () => {
            if (!terminated) {
                terminate();
            }
        });

        initialize();
        iframe.src = config.entryPointUrl;
        updateStatus('Player SCORM pronto');
    }
}

function normalizeVersion(version) {
    const normalized = String(version ?? '').toLowerCase();

    if (normalized === '1.1') {
        return '1.1';
    }

    if (normalized.includes('2004')) {
        return '2004';
    }

    return '1.2';
}
