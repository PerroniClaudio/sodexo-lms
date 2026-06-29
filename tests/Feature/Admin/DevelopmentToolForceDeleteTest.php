<?php

use App\Http\Middleware\EnsureDevelopmentEnvironment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ScormSession;
use App\Models\ScormTracking;
use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use App\Services\ScormService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Storage;

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

it('force deleting a course enrollment also removes scorm runtime data for that enrollment', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $learner = User::factory()->create();
    $learner->assignRole('user');

    $course = Course::factory()->published()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM',
        'belongsTo' => (string) $course->getKey(),
    ]);

    Storage::fake('local');

    $package = app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'imsmanifest.xml' => validScormManifest(),
        'lesson/index.html' => '<html><body>SCORM lesson</body></html>',
    ]));

    $enrollment = CourseEnrollment::enroll($learner, $course);
    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();

    app(ScormService::class)->initializeRuntime($learner, $package, $progress, 'ITEM-DEFAULT', 'force-delete-session');
    app(ScormService::class)->commitRuntime($learner, $package, $progress, 'ITEM-DEFAULT', 'force-delete-session', [
        'cmi.core.lesson_location' => 'page-4',
        'cmi.core.lesson_status' => 'incomplete',
    ]);

    expect(ScormTracking::query()->where('course_user_id', $enrollment->getKey())->exists())->toBeTrue();
    expect(ScormSession::query()->where('course_user_id', $enrollment->getKey())->exists())->toBeTrue();

    $this->post(route('admin.development-tools.force-delete-enrollments.store'), [
        'target_type' => 'course',
        'target_id' => $enrollment->getKey(),
    ])->assertRedirect();

    expect(CourseEnrollment::withTrashed()->find($enrollment->getKey()))->toBeNull();
    expect(ScormTracking::query()->where('course_user_id', $enrollment->getKey())->exists())->toBeFalse();
    expect(ScormSession::query()->where('course_user_id', $enrollment->getKey())->exists())->toBeFalse();
});
