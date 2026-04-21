<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ScormSession;
use App\Models\User;
use App\Services\ScormService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createReadyScormPackage(Module $module)
{
    Storage::fake('local');

    return app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'imsmanifest.xml' => validScormManifest(),
        'lesson/index.html' => '<html><body>SCORM lesson</body></html>',
    ]));
}

it('initializes runtime, persists values, resumes state and terminates the session', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $package = createReadyScormPackage($module);
    $enrollment = CourseEnrollment::enroll($user, $course);
    $moduleProgress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();

    $initializeResponse = $this->postJson(route('user.courses.modules.scorm.runtime.initialize', [$course, $module, $package]), [
        'session_id' => 'session-1',
        'sco_identifier' => 'ITEM-DEFAULT',
        'version' => '1.2',
    ]);

    $initializeResponse->assertOk()->assertJsonPath('success', true);

    expect($initializeResponse->json('state')['cmi.core.lesson_status'] ?? null)->toBe('not attempted');

    $moduleProgress->refresh();
    expect($moduleProgress->status)->toBe(ModuleProgress::STATUS_IN_PROGRESS);

    $this->postJson(route('user.courses.modules.scorm.runtime.set-value', [$course, $module, $package]), [
        'session_id' => 'session-1',
        'sco_identifier' => 'ITEM-DEFAULT',
        'element' => 'cmi.core.lesson_location',
        'value' => 'page-7',
    ])->assertOk();

    $this->postJson(route('user.courses.modules.scorm.runtime.set-value', [$course, $module, $package]), [
        'session_id' => 'session-1',
        'sco_identifier' => 'ITEM-DEFAULT',
        'element' => 'cmi.suspend_data',
        'value' => '{"slide":7}',
    ])->assertOk();

    $getValueResponse = $this->postJson(route('user.courses.modules.scorm.runtime.get-value', [$course, $module, $package]), [
        'session_id' => 'session-1',
        'sco_identifier' => 'ITEM-DEFAULT',
        'element' => 'cmi.core.lesson_location',
    ]);

    $getValueResponse
        ->assertOk()
        ->assertJsonPath('value', 'page-7');

    $commitResponse = $this->postJson(route('user.courses.modules.scorm.runtime.commit', [$course, $module, $package]), [
        'session_id' => 'session-1',
        'sco_identifier' => 'ITEM-DEFAULT',
        'values' => [
            'cmi.core.session_time' => '0000:00:45',
            'cmi.core.lesson_status' => 'incomplete',
        ],
    ]);

    $commitResponse->assertOk()->assertJsonPath('success', true);

    expect($commitResponse->json('state')['cmi.core.lesson_location'] ?? null)->toBe('page-7');
    expect($commitResponse->json('state')['cmi.suspend_data'] ?? null)->toBe('{"slide":7}');

    $moduleProgress->refresh();
    expect($moduleProgress->time_spent_seconds)->toBe(45);

    $resumeResponse = $this->postJson(route('user.courses.modules.scorm.runtime.initialize', [$course, $module, $package]), [
        'session_id' => 'session-2',
        'sco_identifier' => 'ITEM-DEFAULT',
        'version' => '1.2',
    ]);

    $resumeResponse->assertOk();

    expect($resumeResponse->json('state')['cmi.core.lesson_location'] ?? null)->toBe('page-7');
    expect($resumeResponse->json('state')['cmi.suspend_data'] ?? null)->toBe('{"slide":7}');
    expect($resumeResponse->json('state')['cmi.core.entry'] ?? null)->toBe('resume');

    $terminateResponse = $this->postJson(route('user.courses.modules.scorm.runtime.terminate', [$course, $module, $package]), [
        'session_id' => 'session-1',
        'sco_identifier' => 'ITEM-DEFAULT',
        'values' => [
            'cmi.core.session_time' => '0000:01:00',
            'cmi.core.lesson_status' => 'completed',
        ],
    ]);

    $terminateResponse
        ->assertOk()
        ->assertJsonPath('success', true);

    $moduleProgress->refresh();
    $enrollment->refresh();

    expect($moduleProgress->status)->toBe(ModuleProgress::STATUS_COMPLETED);
    expect($enrollment->status)->toBe(CourseEnrollment::STATUS_COMPLETED);
    expect($enrollment->completion_percentage)->toBe(100);
    expect($moduleProgress->time_spent_seconds)->toBe(60);
    expect(ScormSession::query()->where('session_id', 'session-1')->value('status'))->toBe('terminated');
});

it('launches the SCORM player for the enrolled learner', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);
    $this->withoutVite();

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $package = createReadyScormPackage($module);
    CourseEnrollment::enroll($user, $course);

    $launchResponse = $this->post(route('user.courses.modules.scorm.launch', [$course, $module, $package]));

    $launchResponse->assertRedirect(route('user.courses.modules.scorm.player', [$course, $module, $package]));

    $playerResponse = $this->get(route('user.courses.modules.scorm.player', [$course, $module, $package]));

    $playerResponse
        ->assertOk()
        ->assertSeeText('SCORM Player')
        ->assertSee('data-scorm-player-config', escape: false);
});

it('blocks SCORM access for non enrolled users', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $package = createReadyScormPackage($module);

    $response = $this->postJson(route('user.courses.modules.scorm.runtime.initialize', [$course, $module, $package]), [
        'session_id' => 'session-unauthorized',
        'sco_identifier' => 'ITEM-DEFAULT',
        'version' => '1.2',
    ]);

    $response->assertNotFound();
});
