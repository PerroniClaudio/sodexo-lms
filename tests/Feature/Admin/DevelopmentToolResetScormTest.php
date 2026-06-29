<?php

use App\Http\Middleware\EnsureDevelopmentEnvironment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ScormSession;
use App\Models\ScormTracking;
use App\Models\ScormTrackingArchive;
use App\Models\User;
use App\Services\ScormService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    actingAsRole('superadmin');
    $this->withoutVite();
    $this->withoutMiddleware(EnsureDevelopmentEnvironment::class);
});

it('resets course enrollment progress and clears scorm runtime data for that enrollment', function () {
    $learner = User::factory()->create();
    $learner->assignRole('user');

    $course = Course::factory()->create();
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

    app(ScormService::class)->initializeRuntime(
        $learner,
        $package,
        $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail(),
        'ITEM-DEFAULT',
        'reset-session-1',
    );

    app(ScormService::class)->commitRuntime(
        $learner,
        $package,
        $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail(),
        'ITEM-DEFAULT',
        'reset-session-1',
        [
            'cmi.core.lesson_location' => 'page-12',
            'cmi.suspend_data' => '{"slide":12}',
            'cmi.core.lesson_status' => 'incomplete',
        ],
    );

    expect(ScormTracking::query()->where('course_user_id', $enrollment->getKey())->exists())->toBeTrue();
    expect(ScormSession::query()->where('course_user_id', $enrollment->getKey())->exists())->toBeTrue();

    $this->post(route('admin.development-tools.reset-enrollments.store'), [
        'target_type' => 'course',
        'target_id' => $enrollment->getKey(),
        'force_reset' => true,
    ])->assertRedirect();

    expect(ScormTracking::query()->where('course_user_id', $enrollment->getKey())->exists())->toBeFalse();
    expect(ScormSession::query()->where('course_user_id', $enrollment->getKey())->exists())->toBeFalse();
    expect(ScormTrackingArchive::query()
        ->where('course_user_id', $enrollment->getKey())
        ->where('module_id', $module->getKey())
        ->exists())->toBeTrue();

    $this->actingAs($learner);

    $response = $this->postJson(route('user.courses.modules.scorm.runtime.initialize', [$course, $module, $package]), [
        'session_id' => 'reset-session-2',
        'sco_identifier' => 'ITEM-DEFAULT',
        'version' => '1.2',
    ]);

    $response->assertOk();
    expect($response->json('state.cmi.core.lesson_location'))->toBe('');
    expect($response->json('state.cmi.suspend_data'))->toBe('');
    expect($response->json('state.cmi.core.entry'))->toBe('ab-initio');
});

it('archives each scorm reset in a distinct batch and shows the reset count in the superadmin module view', function () {
    $superadmin = auth()->user();

    $learner = User::factory()->create();
    $learner->assignRole('user');

    $course = Course::factory()->create();
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
    $service = app(ScormService::class);
    $moduleProgress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();

    $service->initializeRuntime($learner, $package, $moduleProgress, 'ITEM-DEFAULT', 'reset-batch-1');
    $service->commitRuntime($learner, $package, $moduleProgress, 'ITEM-DEFAULT', 'reset-batch-1', [
        'cmi.core.lesson_location' => 'page-3',
        'cmi.suspend_data' => '{"slide":3}',
    ]);

    $this->post(route('admin.development-tools.reset-enrollments.store'), [
        'target_type' => 'course',
        'target_id' => $enrollment->getKey(),
        'force_reset' => true,
    ])->assertRedirect();

    $this->actingAs($learner);

    $this->postJson(route('user.courses.modules.scorm.runtime.initialize', [$course, $module, $package]), [
        'session_id' => 'reset-batch-2',
        'sco_identifier' => 'ITEM-DEFAULT',
        'version' => '1.2',
    ])->assertOk();

    $this->postJson(route('user.courses.modules.scorm.runtime.commit', [$course, $module, $package]), [
        'session_id' => 'reset-batch-2',
        'sco_identifier' => 'ITEM-DEFAULT',
        'values' => [
            'cmi.core.lesson_location' => 'page-8',
            'cmi.suspend_data' => '{"slide":8}',
        ],
    ])->assertOk();

    $this->actingAs($superadmin);

    $this->post(route('admin.development-tools.reset-enrollments.store'), [
        'target_type' => 'course',
        'target_id' => $enrollment->getKey(),
        'force_reset' => true,
    ])->assertRedirect();

    $resetBatchCount = ScormTrackingArchive::query()
        ->where('course_user_id', $enrollment->getKey())
        ->where('module_id', $module->getKey())
        ->distinct()
        ->pluck('reset_batch_uuid')
        ->count();

    expect($resetBatchCount)->toBe(2);

    $this->get(route('admin.courses.modules.edit', [$course, $module]))
        ->assertOk()
        ->assertSeeText('2 azzeramenti SCORM');
});
