<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
});

it('realigns module progresses and current module when restoring a course enrollment', function () {
    $course = Course::factory()->create([
        'status' => 'draft',
    ]);
    $firstModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'order' => 1,
        'status' => 'published',
    ]);
    $secondModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'order' => 2,
        'status' => 'published',
    ]);
    $user = User::factory()->create();

    $course->forceFill(['status' => 'published'])->saveQuietly();

    $enrollment = CourseEnrollment::enroll($user, $course);
    $enrollment->forceFill([
        'current_module_id' => $secondModule->getKey(),
    ])->saveQuietly();
    $enrollment->delete();

    $enrollment->moduleProgresses()
        ->where('module_id', $firstModule->getKey())
        ->update(['status' => 'available']);
    $enrollment->moduleProgresses()
        ->where('module_id', $secondModule->getKey())
        ->update(['status' => 'locked']);

    $this->postJson(route('admin.api.courses.enrollments.restore', [$course, $enrollment]))
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    expect($enrollment->fresh()->trashed())->toBeFalse()
        ->and((int) $enrollment->fresh()->current_module_id)->toBe((int) $firstModule->getKey())
        ->and(
            $enrollment->fresh()
                ->moduleProgresses()
                ->where('module_id', $firstModule->getKey())
                ->value('status')
        )->toBe('available')
        ->and(
            $enrollment->fresh()
                ->moduleProgresses()
                ->where('module_id', $secondModule->getKey())
                ->value('status')
        )->toBe('locked');
});

it('restores a training path enrollment and recreates missing course enrollments in order', function () {
    $trainingPath = TrainingPath::factory()->create([
        'status' => 'published',
        'enforce_course_order' => true,
    ]);
    $firstCourse = Course::factory()->create([
        'status' => 'draft',
    ]);
    $secondCourse = Course::factory()->create([
        'status' => 'draft',
    ]);
    Module::factory()->create([
        'belongsTo' => (string) $firstCourse->getKey(),
        'order' => 1,
        'status' => 'published',
    ]);
    Module::factory()->create([
        'belongsTo' => (string) $secondCourse->getKey(),
        'order' => 1,
        'status' => 'published',
    ]);
    $firstCourse->forceFill(['status' => 'published'])->saveQuietly();
    $secondCourse->forceFill(['status' => 'published'])->saveQuietly();
    $trainingPath->courses()->attach($firstCourse->getKey(), ['sort_order' => 1]);
    $trainingPath->courses()->attach($secondCourse->getKey(), ['sort_order' => 2]);

    $user = User::factory()->create();
    $trainingPathEnrollment = TrainingPathEnrollment::enroll($user, $trainingPath);
    $trainingPathEnrollment->delete();

    CourseEnrollment::enroll($user, $secondCourse, directOrigin: false, pathwayOrigin: true);

    $this->postJson(route('admin.api.training-paths.enrollments.restore', [$trainingPath, $trainingPathEnrollment]))
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $restoredEnrollment = $trainingPathEnrollment->fresh();

    expect($restoredEnrollment->trashed())->toBeFalse()
        ->and((int) $restoredEnrollment->current_course_id)->toBe((int) $firstCourse->getKey())
        ->and(
            CourseEnrollment::query()
                ->where('user_id', $user->getKey())
                ->where('course_id', $firstCourse->getKey())
                ->whereNull('deleted_at')
                ->exists()
        )->toBeTrue()
        ->and(
            CourseEnrollment::query()
                ->where('user_id', $user->getKey())
                ->where('course_id', $secondCourse->getKey())
                ->whereNull('deleted_at')
                ->exists()
        )->toBeTrue();
});
