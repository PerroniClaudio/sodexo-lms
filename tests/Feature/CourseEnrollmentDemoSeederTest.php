<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use Database\Seeders\CourseEnrollmentDemoSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it seeds a demo course with three modules and two enrolled users with expected roles', function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        CourseEnrollmentDemoSeeder::class,
    ]);

    $course = Course::query()
        ->where('title', 'Corso demo iscrizioni')
        ->first();

    expect($course)->not->toBeNull();
    expect($course->status)->toBe('published');

    $modules = Module::query()
        ->where('belongsTo', (string) $course->getKey())
        ->orderBy('order')
        ->get();

    expect($modules)->toHaveCount(3);
    expect($modules->pluck('type')->all())->toBe([
        'live',
        'learning_quiz',
        'satisfaction_quiz',
    ]);
    expect($modules->first()->is_live_teacher)->toBeTrue();

    $docente = User::query()->where('email', 'docente-corso-demo@test.com')->first();
    $utente = User::query()->where('email', 'utente-corso-demo@test.com')->first();

    expect($docente)->not->toBeNull();
    expect($utente)->not->toBeNull();
    expect($docente->hasRole('docente'))->toBeTrue();
    expect($utente->hasRole('user'))->toBeTrue();
    expect($docente->hasPermissionTo('manage attendance'))->toBeTrue();
    expect($utente->hasPermissionTo('view courses'))->toBeTrue();
    expect($utente->hasPermissionTo('manage attendance'))->toBeFalse();

    $enrollments = CourseEnrollment::query()
        ->where('course_id', $course->getKey())
        ->orderBy('id')
        ->get();

    expect($enrollments)->toHaveCount(2);

    foreach ($enrollments as $enrollment) {
        expect($enrollment->status)->toBe(CourseEnrollment::STATUS_ASSIGNED);
        expect($enrollment->current_module_id)->toBe($modules->first()->getKey());
        expect($enrollment->completion_percentage)->toBe(0);
        expect($enrollment->moduleProgresses()->count())->toBe(3);
        expect($enrollment->moduleProgresses()->where('status', ModuleProgress::STATUS_AVAILABLE)->count())->toBe(1);
        expect($enrollment->moduleProgresses()->where('status', ModuleProgress::STATUS_LOCKED)->count())->toBe(2);
    }
});
