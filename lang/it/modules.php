<?php

return [
    'types' => [
        'video' => 'Video',
        'residential' => 'Residenziale',
        'live' => 'Live',
        'scorm' => 'SCORM',
        'learning_quiz' => 'Quiz di apprendimento',
        'satisfaction_quiz' => 'Questionario di gradimento',
    ],
    'statuses' => [
        'draft' => 'Bozza',
        'published' => 'Pubblicato',
        'archived' => 'Archiviato',
    ],
    'fields' => [
        'type' => 'Tipologia modulo',
        'title' => 'Titolo del modulo',
    ],
    'messages' => [
        'created' => 'Modulo creato con successo.',
        'updated' => 'Modulo aggiornato con successo.',
        'deleted' => 'Modulo eliminato con successo.',
        'order_updated' => 'Ordine moduli aggiornato con successo.',
        'invalid_order' => "L'ordinamento dei moduli non è valido.",
        'restricted_type' => 'Il corso con tipologia :course_type non può contenere un modulo :module_type.',
    ],
];
