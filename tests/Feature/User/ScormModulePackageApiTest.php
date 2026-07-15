<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\User;
use App\Services\ScormService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Storage;

function createScormLearnerContext(): array
{
    test()->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('user');
    test()->actingAs($user);

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM API',
        'belongsTo' => (string) $course->getKey(),
    ]);

    Storage::fake('local');

    $package = app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'imsmanifest.xml' => validScormManifest(),
        'lesson/index.html' => '<html><body>SCORM lesson</body></html>',
    ]));

    $enrollment = CourseEnrollment::enroll($user, $course);
    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();

    return [$user, $course, $module, $package, $enrollment, $progress];
}

test('scorm packages api returns learner-facing package summaries', function () {
    [$user, $course, $module, $package, , $progress] = createScormLearnerContext();

    $service = app(ScormService::class);

    $service->initializeRuntime($user, $package, $progress, 'ITEM-DEFAULT', 'api-session-1');
    $service->persistTrackingValue($user, $package, 'ITEM-DEFAULT', 'cmi.core.lesson_location', 'slide-4', 'api-session-1', $progress);
    $service->commitRuntime($user, $package, $progress, 'ITEM-DEFAULT', 'api-session-1', [
        'cmi.core.session_time' => '0000:01:15',
        'cmi.core.lesson_status' => 'incomplete',
        'cmi.core.score.raw' => '85',
        'cmi.core.score.max' => '100',
        'cmi.progress_measure' => '0.85',
    ]);

    $response = $this->getJson(route('user.courses.modules.scorm.packages.index', [$course, $module]));

    $response->assertOk()
        ->assertJsonPath('packages.0.id', $package->getKey())
        ->assertJsonPath('packages.0.player_url', route('user.courses.modules.scorm.player', [$course, $module, $package, 'sco' => 'ITEM-DEFAULT']))
        ->assertJsonPath('packages.0.learner_status', 'In corso')
        ->assertJsonPath('packages.0.progress_percent', 85)
        ->assertJsonPath('packages.0.lesson_location', 'slide-4')
        ->assertJsonPath('packages.0.score.display', '85 / 100');
});

test('scorm packages api keeps summaries isolated between multiple packages in the same module', function () {
    [$user, $course, $module, $package, , $progress] = createScormLearnerContext();

    $service = app(ScormService::class);

    $secondPackage = app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'imsmanifest.xml' => validScormManifest(),
        'lesson/index.html' => '<html><body>Second SCORM lesson</body></html>',
    ]));

    $service->initializeRuntime($user, $package, $progress, 'ITEM-DEFAULT', 'api-session-package-1');
    $service->commitRuntime($user, $package, $progress, 'ITEM-DEFAULT', 'api-session-package-1', [
        'cmi.core.lesson_location' => 'slide-8',
        'cmi.progress_measure' => '0.8',
        'cmi.core.lesson_status' => 'incomplete',
    ]);

    $service->initializeRuntime($user, $secondPackage, $progress, 'ITEM-DEFAULT', 'api-session-package-2');
    $service->commitRuntime($user, $secondPackage, $progress, 'ITEM-DEFAULT', 'api-session-package-2', [
        'cmi.core.lesson_location' => 'slide-2',
        'cmi.progress_measure' => '0.2',
        'cmi.core.lesson_status' => 'incomplete',
    ]);

    $response = $this->getJson(route('user.courses.modules.scorm.packages.index', [$course, $module]));

    $firstSummary = collect($response->json('packages'))->firstWhere('id', $package->getKey());
    $secondSummary = collect($response->json('packages'))->firstWhere('id', $secondPackage->getKey());

    expect($firstSummary)->not->toBeNull()
        ->and($secondSummary)->not->toBeNull()
        ->and(data_get($firstSummary, 'lesson_location'))->toBe('slide-8')
        ->and(data_get($firstSummary, 'progress_percent'))->toBe(80)
        ->and(data_get($secondSummary, 'lesson_location'))->toBe('slide-2')
        ->and(data_get($secondSummary, 'progress_percent'))->toBe(20);
});
