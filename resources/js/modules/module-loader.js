/**
 * module-loader.js
 * Script di inizializzazione che carica il modulo corretto in base al tipo.
 */

import { getModuleRoot, getModuleData, pollModuleAccess } from './module-base.js';

const root = getModuleRoot();

if (root) {
    const moduleData = getModuleData(root);
    const moduleType = moduleData.moduleType;

    if (root.dataset.accessGateActive === 'true') {
        const timer = root.querySelector('[data-module-access-gate-timer]');
        let seconds = parseInt(root.dataset.accessGateRemainingSeconds ?? '0', 10);
        const tick = () => {
            seconds = Math.max(0, seconds);
            const hours = String(Math.floor(seconds / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
            const remainingSeconds = String(seconds % 60).padStart(2, '0');
            timer.textContent = `${hours}:${minutes}:${remainingSeconds}`;
            seconds -= seconds > 0 ? 1 : 0;
            window.setTimeout(tick, 1000);
        };

        tick();
        pollModuleAccess(moduleData, Number(moduleData.moduleId), (module) => {
            if (! module.access_gate_active) {
                window.location.reload();
            }
        });
    } else {
        // Carica il modulo corretto in base al tipo
        switch (moduleType) {
        case 'video':
            import('./module-video.js').then(({ initVideoModule }) => {
                initVideoModule();
            });
            break;

        case 'learning_quiz':
            import('./module-learning-quiz.js').then(({ initLearningQuizModule }) => {
                initLearningQuizModule();
            });
            break;

        case 'satisfaction_quiz':
            import('./module-satisfaction-quiz.js').then(({ initSatisfactionQuizModule }) => {
                initSatisfactionQuizModule();
            });
            break;

        case 'live':
            // TODO: Implementare quando disponibile
            console.info('[module-loader] Modulo live non ancora implementato');
            break;

        case 'scorm':
            import('./module-scorm.js').then(({ initScormModule }) => {
                initScormModule();
            });
            break;

        case 'residential':
            // TODO: Implementare quando disponibile
            console.info('[module-loader] Modulo residential non ancora implementato');
            break;

            default:
                console.warn(`[module-loader] Tipo di modulo sconosciuto: ${moduleType}`);
        }
    }
}
