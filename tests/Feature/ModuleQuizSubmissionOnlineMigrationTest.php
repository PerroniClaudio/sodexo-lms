<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('online quiz submission migration may run on an already adapted schema', function () {
    $migration = include base_path('database/migrations/2026_05_08_111407_adapt_module_quiz_submissions_for_online_quizzes.php');

    expect(fn () => $migration->up())->not->toThrow(Throwable::class);

    expect(Schema::hasColumn('module_quiz_submissions', 'source_type'))->toBeTrue();
    expect(Schema::hasColumn('module_quiz_submissions', 'course_enrollment_id'))->toBeTrue();
    expect(Schema::hasColumn('module_quiz_submissions', 'started_at'))->toBeTrue();
    expect(Schema::hasColumn('module_quiz_submissions', 'submitted_at'))->toBeTrue();
    expect(Schema::hasIndex('module_quiz_submissions', ['course_enrollment_id', 'module_id', 'created_at']))->toBeTrue();
});
