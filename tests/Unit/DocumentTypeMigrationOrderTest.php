<?php

test('document types are created before they are referenced', function () {
    $migrations = collect(glob(database_path('migrations/*.php')))
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values();

    expect($migrations->search('2026_06_03_112055_create_document_types_table.php'))
        ->toBeLessThan($migrations->search('2026_06_03_112056_add_document_type_id_to_module_quiz_document_uploads_table.php'));
});
