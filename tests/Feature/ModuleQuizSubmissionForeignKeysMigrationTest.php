<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('module quiz submission answers define the submission foreign key after migrations', function () {
    $submissionAnswerForeignKeys = collect(Schema::getForeignKeys('module_quiz_submission_answers'))
        ->keyBy(fn (array $foreignKey): string => $foreignKey['columns'][0]);

    expect($submissionAnswerForeignKeys['module_quiz_submission_id']['foreign_table'] ?? null)
        ->toBe('module_quiz_submissions');
});
