<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use App\Services\SyncCourseModuleProgresses;
use App\Services\TrainingPathEnrollmentSyncService;
use Database\Seeders\RoleAndPermissionSeeder;

it('does not sync training paths when only access metadata changes on an enrollment', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $course = Course::factory()->create();

    $enrollment = CourseEnrollment::withoutEvents(fn () => CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
    ]));

    $syncService = Mockery::mock(TrainingPathEnrollmentSyncService::class);
    $syncService->shouldNotReceive('syncAllEnrollmentsForUser');
    app()->instance(TrainingPathEnrollmentSyncService::class, $syncService);

    $enrollment->update([
        'last_accessed_at' => now(),
    ]);

    expect($enrollment->fresh()->last_accessed_at)->not->toBeNull();
});

it('does not resync module progresses for already active path enrollments', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $course = Course::factory()->create([
        'status' => 'published',
    ]);
    $trainingPath = TrainingPath::factory()->create([
        'status' => 'published',
    ]);
    $trainingPath->courses()->attach($course->getKey());

    CourseEnrollment::withoutEvents(fn () => CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'direct_origin' => false,
        'pathway_origin' => true,
    ]));

    $trainingPathEnrollment = TrainingPathEnrollment::withoutEvents(fn () => TrainingPathEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'training_path_id' => $trainingPath->getKey(),
    ]));

    $moduleProgressSync = Mockery::mock(SyncCourseModuleProgresses::class);
    $moduleProgressSync->shouldNotReceive('handle');
    app()->instance(SyncCourseModuleProgresses::class, $moduleProgressSync);

    $service = app(TrainingPathEnrollmentSyncService::class);

    expect($service->syncEnrollment($trainingPathEnrollment->fresh()))
        ->toBeTrue();
});
