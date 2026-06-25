<?php

return [
    'types' => [
        'video' => 'Video',
        'residential' => 'Residential',
        'live' => 'Live',
        'scorm' => 'SCORM',
        'learning_quiz' => 'Learning quiz',
        'satisfaction_quiz' => 'Satisfaction survey',
    ],
    'statuses' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ],
    'fields' => [
        'type' => 'Module type',
        'title' => 'Module title',
    ],
    'messages' => [
        'created' => 'Module created successfully.',
        'updated' => 'Module updated successfully.',
        'deleted' => 'Module deleted successfully.',
        'order_updated' => 'Module order updated successfully.',
        'invalid_order' => 'The module order is invalid.',
        'restricted_type' => 'A course with type :course_type cannot contain a :module_type module.',
    ],
];
