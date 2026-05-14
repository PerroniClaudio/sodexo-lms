<?php

use App\Models\Course;
use App\Models\CourseTeacherEnrollment;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('teacher enrollment stores only the course assignment without learner progress fields', function () {
    $teacher = actingAsRole('teacher');
    $course = Course::factory()->create();

    Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'type' => 'live',
    ]);

    Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'type' => 'learning_quiz',
    ]);

    Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'type' => 'satisfaction_quiz',
    ]);

    $enrollment = CourseTeacherEnrollment::enroll($teacher, $course);

    expect($enrollment->course_id)->toBe($course->getKey());
    expect($enrollment->user_id)->toBe($teacher->getKey());
    expect($enrollment->getAttributes())->not->toHaveKeys([
        'current_module_id',
        'completion_percentage',
        'status',
    ]);
});
