<?php

use App\Http\Middleware\EnsureDevelopmentEnvironment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use App\Models\User;

beforeEach(function () {
    actingAsRole('superadmin');
    $this->withoutVite();
    $this->withoutMiddleware(EnsureDevelopmentEnvironment::class);
});

it('shows the force delete enrollments development tool page', function () {
    $this->get(route('admin.development-tools.force-delete-enrollments.index'))
        ->assertOk()
        ->assertSee('Force delete iscrizioni');
});

it('force deletes a course enrollment only when no pathway origin remains', function () {
    $course = Course::factory()->published()->create();
    $trainingPath = TrainingPath::factory()->create([
        'status' => 'published',
    ]);
    $trainingPath->courses()->attach($course->getKey(), ['sort_order' => 1]);

    $user = User::factory()->create();
    TrainingPathEnrollment::enroll($user, $trainingPath);

    $courseEnrollment = CourseEnrollment::enroll($user, $course, directOrigin: true, pathwayOrigin: true);

    $this->post(route('admin.development-tools.force-delete-enrollments.store'), [
        'target_type' => 'course',
        'target_id' => $courseEnrollment->getKey(),
    ])->assertRedirect();

    expect($courseEnrollment->fresh())
        ->not->toBeNull()
        ->and($courseEnrollment->fresh()->direct_origin)->toBeFalse()
        ->and($courseEnrollment->fresh()->pathway_origin)->toBeTrue();

    $pathEnrollment = TrainingPathEnrollment::query()->firstOrFail();
    $pathEnrollment->delete();

    $this->post(route('admin.development-tools.force-delete-enrollments.store'), [
        'target_type' => 'course',
        'target_id' => $courseEnrollment->getKey(),
    ])->assertRedirect();

    expect(CourseEnrollment::withTrashed()->find($courseEnrollment->getKey()))->toBeNull();
});

it('force deletes a training path enrollment and preserves only valid course origins', function () {
    $sharedCourse = Course::factory()->published()->create();
    $pathOnlyCourse = Course::factory()->published()->create();
    $firstPath = TrainingPath::factory()->create([
        'status' => 'published',
    ]);
    $secondPath = TrainingPath::factory()->create([
        'status' => 'published',
    ]);

    $firstPath->courses()->attach($sharedCourse->getKey(), ['sort_order' => 1]);
    $firstPath->courses()->attach($pathOnlyCourse->getKey(), ['sort_order' => 2]);
    $secondPath->courses()->attach($sharedCourse->getKey(), ['sort_order' => 1]);

    $user = User::factory()->create();
    $firstPathEnrollment = TrainingPathEnrollment::enroll($user, $firstPath);
    TrainingPathEnrollment::enroll($user, $secondPath);

    $sharedEnrollment = CourseEnrollment::enroll($user, $sharedCourse, directOrigin: true, pathwayOrigin: true);
    $pathOnlyEnrollment = CourseEnrollment::query()
        ->where('user_id', $user->getKey())
        ->where('course_id', $pathOnlyCourse->getKey())
        ->firstOrFail();

    $this->post(route('admin.development-tools.force-delete-enrollments.store'), [
        'target_type' => 'training_path',
        'target_id' => $firstPathEnrollment->getKey(),
    ])->assertRedirect();

    expect(TrainingPathEnrollment::withTrashed()->find($firstPathEnrollment->getKey()))->toBeNull()
        ->and($sharedEnrollment->fresh())
        ->not->toBeNull()
        ->and($sharedEnrollment->fresh()->direct_origin)->toBeTrue()
        ->and($sharedEnrollment->fresh()->pathway_origin)->toBeTrue()
        ->and(CourseEnrollment::withTrashed()->find($pathOnlyEnrollment->getKey()))->toBeNull();
});
