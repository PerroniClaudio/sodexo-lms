<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseTeacherEnrollment;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\AsyncLiveCourseDemoSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it seeds an async course with one live module, one enrolled learner, and one assigned teacher', function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AsyncLiveCourseDemoSeeder::class,
    ]);

    $course = Course::query()
        ->where('title', 'Corso demo FAD asincrono con live')
        ->first();

    expect($course)->not->toBeNull();
    expect($course->type)->toBe('async');
    expect($course->status)->toBe('published');

    $module = Module::query()
        ->where('belongsTo', (string) $course->getKey())
        ->where('order', 1)
        ->first();

    expect($module)->not->toBeNull();
    expect($module->type)->toBe('live');
    expect($module->is_live_teacher)->toBeTrue();

    $teacher = User::query()->where('email', 'docente-live-async-demo@test.com')->first();
    $user = User::query()->where('email', 'utente-live-async-demo@test.com')->first();

    expect($teacher)->not->toBeNull();
    expect($user)->not->toBeNull();
    expect($teacher->hasRole('docente'))->toBeTrue();
    expect($user->hasRole('user'))->toBeTrue();

    expect(
        CourseTeacherEnrollment::query()
            ->where('user_id', $teacher->getKey())
            ->where('course_id', $course->getKey())
            ->whereNull('deleted_at')
            ->exists()
    )->toBeTrue();

    $enrollment = CourseEnrollment::query()
        ->where('user_id', $user->getKey())
        ->where('course_id', $course->getKey())
        ->whereNull('deleted_at')
        ->first();

    expect($enrollment)->not->toBeNull();
    expect($enrollment->current_module_id)->toBe($module->getKey());
    expect($enrollment->moduleProgresses()->count())->toBe(1);
});
