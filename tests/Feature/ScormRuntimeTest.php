<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ScormSession;
use App\Models\ScormTracking;
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
    expect(ScormTracking::query()->where('scorm_package_id', $package->getKey())->count())->toBeGreaterThan(0);
    expect(ScormTracking::query()
        ->where('scorm_package_id', $package->getKey())
        ->where('element', 'cmi.core.lesson_location')
        ->exists())->toBeTrue();
    expect(ScormTracking::query()
        ->where('scorm_package_id', $package->getKey())
        ->where('element', '__meta.last_location')
        ->exists())->toBeTrue();
});

it('persists scorm 2004 values, progress and interaction data', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM 2004',
        'belongsTo' => (string) $course->getKey(),
    ]);

    Storage::fake('local');

    $package = app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'imsmanifest.xml' => validScorm2004Manifest(),
        'lesson2004/index.html' => '<html><body>SCORM 2004 lesson</body></html>',
        'lesson2004/assessment.html' => '<html><body>Assessment lesson</body></html>',
    ]));

    CourseEnrollment::enroll($user, $course);

    $this->postJson(route('user.courses.modules.scorm.runtime.initialize', [$course, $module, $package]), [
        'session_id' => 'session-2004',
        'sco_identifier' => 'ITEM-2004',
        'version' => '2004',
    ])->assertOk();

    $this->postJson(route('user.courses.modules.scorm.runtime.commit', [$course, $module, $package]), [
        'session_id' => 'session-2004',
        'sco_identifier' => 'ITEM-2004',
        'values' => [
            'cmi.location' => '12',
            'cmi.progress_measure' => '0.92',
            'cmi.completion_status' => 'completed',
            'cmi.success_status' => 'passed',
            'cmi.score.raw' => '97',
            'cmi.interactions.0.id' => 'final-test-q1',
            'cmi.interactions.0.learner_response' => 'B',
            'cmi.session_time' => 'PT0H1M30S',
        ],
    ])->assertOk();

    expect(ScormTracking::query()
        ->where('scorm_package_id', $package->getKey())
        ->where('element', 'cmi.location')
        ->value('value'))->toBe('12');
    expect(ScormTracking::query()
        ->where('scorm_package_id', $package->getKey())
        ->where('element', 'cmi.interactions.0.learner_response')
        ->value('value'))->toBe('B');
    expect(ScormTracking::query()
        ->where('scorm_package_id', $package->getKey())
        ->where('element', '__meta.max_progress_measure')
        ->exists())->toBeTrue();
    expect(ScormTracking::query()
        ->where('scorm_package_id', $package->getKey())
        ->where('element', '__meta.max_numeric_location')
        ->exists())->toBeTrue();
});

it('supports scorm 2004 data buckets and jump navigation request', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM 2004 Notes',
        'belongsTo' => (string) $course->getKey(),
    ]);

    Storage::fake('local');

    $package = app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'imsmanifest.xml' => validScorm2004Manifest(),
        'lesson2004/index.html' => '<html><body>SCORM 2004 lesson</body></html>',
        'lesson2004/assessment.html' => '<html><body>Assessment lesson</body></html>',
    ]));

    CourseEnrollment::enroll($user, $course);

    $this->postJson(route('user.courses.modules.scorm.runtime.initialize', [$course, $module, $package]), [
        'session_id' => 'session-2004-notes',
        'sco_identifier' => 'ITEM-2004',
        'version' => '2004',
    ])->assertOk();

    $this->postJson(route('user.courses.modules.scorm.runtime.get-value', [$course, $module, $package]), [
        'session_id' => 'session-2004-notes',
        'sco_identifier' => 'ITEM-2004',
        'element' => 'adl.data._count',
    ])->assertOk()->assertJsonPath('value', '1');

    $this->postJson(route('user.courses.modules.scorm.runtime.set-value', [$course, $module, $package]), [
        'session_id' => 'session-2004-notes',
        'sco_identifier' => 'ITEM-2004',
        'element' => 'adl.data.0.store',
        'value' => 'My notes',
    ])->assertOk();

    $this->postJson(route('user.courses.modules.scorm.runtime.get-value', [$course, $module, $package]), [
        'session_id' => 'session-2004-notes',
        'sco_identifier' => 'ITEM-2004',
        'element' => 'adl.data.0.store',
    ])->assertOk()->assertJsonPath('value', 'My notes');

    $terminateResponse = $this->postJson(route('user.courses.modules.scorm.runtime.terminate', [$course, $module, $package]), [
        'session_id' => 'session-2004-notes',
        'sco_identifier' => 'ITEM-2004',
        'values' => [
            'adl.nav.request' => '{target=assessment_item}jump',
            'cmi.session_time' => 'PT0H0M10S',
        ],
    ])->assertOk();

    $terminateResponse->assertJsonPath(
        'navigation.url',
        route('user.courses.modules.scorm.player', [$course, $module, $package, 'sco' => 'assessment_item'])
    );

    $this->get(route('user.courses.modules.scorm.player', [
        $course,
        $module,
        $package,
        'sco' => 'assessment_item',
    ]))
        ->assertOk()
        ->assertSee('content=assessment', escape: false)
        ->assertSee('assessment_item', escape: false);
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
        ->assertSee('data-scorm-player-config', escape: false)
        ->assertSee('data-scorm-player-iframe', escape: false);
});

it('serves scorm json assets without truncating the payload', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM JSON',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $expectedJson = '{"items":[{"id":1,"title":"Intro"}],"meta":{"complete":true}}';

    Storage::fake('local');

    $package = app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'imsmanifest.xml' => validScormManifest(),
        'lesson/index.html' => '<html><body>SCORM lesson</body></html>',
        'lesson/data.json' => $expectedJson,
    ]));

    CourseEnrollment::enroll($user, $course);

    $response = $this->get(route('user.courses.modules.scorm.asset', [
        $course,
        $module,
        $package,
        'path' => 'lesson/data.json',
    ]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertContent($expectedJson);
});

it('serves scorm javascript assets without injecting the html bridge', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM JS',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $expectedJavascript = 'function AddTagLine(){document.write("<div class=\"salespitch\">SCORM</div>");}';

    Storage::fake('local');

    $package = app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'imsmanifest.xml' => validScormManifest(),
        'lesson/index.html' => '<html><body>SCORM lesson</body></html>',
        'shared/contentfunctions.js' => $expectedJavascript,
    ]));

    CourseEnrollment::enroll($user, $course);

    $response = $this->get(route('user.courses.modules.scorm.asset', [
        $course,
        $module,
        $package,
        'path' => 'shared/contentfunctions.js',
    ]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript')
        ->assertContent($expectedJavascript);
});

it('injects the scorm bridge into html assets without truncating the document', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM HTML',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $expectedHtml = '<html><head><title>SCORM</title></head><body><script>window.lesson={"complete":true};</script><div id="root"></div></body></html>';

    Storage::fake('local');

    $package = app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'imsmanifest.xml' => validScormManifest(),
        'lesson/index.html' => $expectedHtml,
    ]));

    CourseEnrollment::enroll($user, $course);

    $response = $this->get(route('user.courses.modules.scorm.asset', [
        $course,
        $module,
        $package,
        'path' => 'lesson/index.html',
    ]));

    $response
        ->assertOk()
        ->assertSee('window.API = window.parent.API;', escape: false)
        ->assertSee('<div id="root"></div></body></html>', escape: false)
        ->assertSee('window.lesson={"complete":true};', escape: false);
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

it('keeps scorm runtime and player reachable after module completion advances current module', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM',
        'belongsTo' => (string) $course->getKey(),
        'order' => 1,
    ]);
    $nextModule = Module::factory()->create([
        'type' => 'video',
        'title' => 'Modulo successivo',
        'belongsTo' => (string) $course->getKey(),
        'order' => 2,
    ]);

    $package = createReadyScormPackage($module);
    $enrollment = CourseEnrollment::enroll($user, $course);

    $this->postJson(route('user.courses.modules.scorm.runtime.initialize', [$course, $module, $package]), [
        'session_id' => 'session-completed',
        'sco_identifier' => 'ITEM-DEFAULT',
        'version' => '1.2',
    ])->assertOk();

    $this->postJson(route('user.courses.modules.scorm.runtime.commit', [$course, $module, $package]), [
        'session_id' => 'session-completed',
        'sco_identifier' => 'ITEM-DEFAULT',
        'values' => [
            'cmi.core.lesson_status' => 'completed',
            'cmi.core.session_time' => '0000:00:30',
        ],
    ])->assertOk();

    $enrollment->refresh();

    expect((int) $enrollment->current_module_id)->toBe($nextModule->getKey());

    $this->postJson(route('user.courses.modules.scorm.runtime.commit', [$course, $module, $package]), [
        'session_id' => 'session-completed',
        'sco_identifier' => 'ITEM-DEFAULT',
        'values' => [
            'cmi.suspend_data' => '{"final":true}',
        ],
    ])->assertOk()->assertJsonPath('state.cmi.suspend_data', '{"final":true}');

    $this->postJson(route('user.courses.modules.scorm.runtime.terminate', [$course, $module, $package]), [
        'session_id' => 'session-completed',
        'sco_identifier' => 'ITEM-DEFAULT',
        'values' => [
            'cmi.core.session_time' => '0000:00:35',
        ],
    ])->assertOk();

    $this->get(route('user.courses.modules.scorm.player', [$course, $module, $package]))
        ->assertOk();
});

it('advances the course when scorm runtime is terminated without an explicit completion status', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user);

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'title' => 'Modulo SCORM',
        'belongsTo' => (string) $course->getKey(),
        'order' => 1,
    ]);
    $nextModule = Module::factory()->create([
        'type' => 'video',
        'title' => 'Modulo successivo',
        'belongsTo' => (string) $course->getKey(),
        'order' => 2,
    ]);

    $package = createReadyScormPackage($module);
    $enrollment = CourseEnrollment::enroll($user, $course);
    $moduleProgress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();

    $this->postJson(route('user.courses.modules.scorm.runtime.initialize', [$course, $module, $package]), [
        'session_id' => 'session-exit',
        'sco_identifier' => 'ITEM-DEFAULT',
        'version' => '1.2',
    ])->assertOk();

    $terminateResponse = $this->postJson(route('user.courses.modules.scorm.runtime.terminate', [$course, $module, $package]), [
        'session_id' => 'session-exit',
        'sco_identifier' => 'ITEM-DEFAULT',
        'values' => [
            'cmi.core.lesson_location' => 'exit-button',
        ],
    ])->assertOk();

    $enrollment->refresh();
    $moduleProgress->refresh();
    $nextProgress = $enrollment->moduleProgresses()->where('module_id', $nextModule->getKey())->firstOrFail();

    expect($moduleProgress->status)->toBe(ModuleProgress::STATUS_COMPLETED);
    expect((int) $enrollment->current_module_id)->toBe($nextModule->getKey());
    expect($nextProgress->status)->toBe(ModuleProgress::STATUS_AVAILABLE);
    expect($terminateResponse->json('redirect_url'))->toBe(route('user.courses.modules.player', [$course, $nextModule]));
    expect(ScormSession::query()->where('session_id', 'session-exit')->value('status'))->toBe(ScormSession::STATUS_TERMINATED);
});
