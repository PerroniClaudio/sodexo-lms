<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleTeacherEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('teacher module enrollment stores only the module assignment without learner progress fields', function () {
    $teacher = actingAsRole('teacher');
    $course = Course::factory()->create();

    $module = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'type' => 'live',
    ]);

    $enrollment = ModuleTeacherEnrollment::enroll($teacher, $module);

    expect($enrollment->module_id)->toBe($module->getKey());
    expect($enrollment->user_id)->toBe($teacher->getKey());
    expect($enrollment->getAttributes())->not->toHaveKeys([
        'current_module_id',
        'completion_percentage',
        'status',
    ]);
});
