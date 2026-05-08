/**
 * module-loader.js
 * Script di inizializzazione che carica il modulo corretto in base al tipo.
 */

import { getModuleRoot, getModuleData } from './module-base.js';

const root = getModuleRoot();

if (root) {
    const moduleData = getModuleData(root);
    const moduleType = moduleData.moduleType;

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

        case 'residential':
            // TODO: Implementare quando disponibile
            console.info('[module-loader] Modulo residential non ancora implementato');
            break;

        default:
            console.warn(`[module-loader] Tipo di modulo sconosciuto: ${moduleType}`);
    }
}
