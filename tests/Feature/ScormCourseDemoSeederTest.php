<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\ScormCourseDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it seeds a demo user enrolled in a course with a scorm module', function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        ScormCourseDemoSeeder::class,
    ]);

    $course = Course::query()
        ->where('title', 'Corso demo SCORM')
        ->first();

    expect($course)->not->toBeNull();
    expect($course->type)->toBe('async');
    expect($course->status)->toBe('published');

    $modules = Module::query()
        ->where('belongsTo', (string) $course->getKey())
        ->orderBy('order')
        ->get();

    expect($modules)->toHaveCount(1);
    expect($modules->first()->type)->toBe('scorm');
    expect($modules->first()->status)->toBe('published');

    $user = User::query()
        ->where('email', 'utente-scorm-demo@test.com')
        ->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('user'))->toBeTrue();
    expect($user->hasPermissionTo('view courses'))->toBeTrue();

    $enrollment = CourseEnrollment::query()
        ->where('user_id', $user->getKey())
        ->where('course_id', $course->getKey())
        ->first();

    expect($enrollment)->not->toBeNull();
    expect($enrollment->status)->toBe(CourseEnrollment::STATUS_ASSIGNED);
    expect($enrollment->current_module_id)->toBe($modules->first()->getKey());
    expect($enrollment->completion_percentage)->toBe(0);

    $moduleProgresses = $enrollment->moduleProgresses()->get();

    expect($moduleProgresses)->toHaveCount(1);
    expect($moduleProgresses->first()->status)->toBe(ModuleProgress::STATUS_AVAILABLE);
});
