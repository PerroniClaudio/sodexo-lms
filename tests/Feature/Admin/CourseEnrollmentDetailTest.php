<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ScormTracking;
use App\Models\ScormTrackingArchive;
use App\Models\User;
use App\Models\Video;
use App\Services\ScormService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
});

it('shows the admin enrollment detail page and exposes its links from admin lists', function () {
    actingAsRole('admin');

    $learner = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.com',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $learner->assignRole('user');

    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
        'status' => 'draft',
    ]);
    $video = Video::factory()->create([
        'duration_seconds' => 600,
    ]);

    $videoModule = Module::factory()->create([
        'title' => 'Video introduttivo',
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
        'video_id' => $video->getKey(),
    ]);
    $quizModule = Module::factory()->create([
        'title' => 'Quiz finale',
        'type' => Module::TYPE_LEARNING_QUIZ,
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
        'passing_score' => 7,
        'max_score' => 10,
        'max_attempts' => 3,
        'status' => 'published',
    ]);

    $course->forceFill(['status' => 'published'])->saveQuietly();

    $enrollment = CourseEnrollment::enroll($learner, $course);
    $enrollment->moduleProgresses()->where('module_id', $videoModule->getKey())->firstOrFail()->forceFill([
        'status' => ModuleProgress::STATUS_COMPLETED,
        'started_at' => now()->subMinutes(15),
        'completed_at' => now()->subMinutes(5),
        'time_spent_seconds' => 480,
        'video_current_second' => 600,
        'video_max_second' => 600,
    ])->saveQuietly();
    $enrollment->moduleProgresses()->where('module_id', $quizModule->getKey())->firstOrFail()->forceFill([
        'status' => ModuleProgress::STATUS_FAILED,
        'quiz_attempts' => 1,
        'quiz_score' => 4,
        'quiz_total_score' => 10,
        'started_at' => now()->subMinutes(4),
    ])->saveQuietly();
    $enrollment->forceFill([
        'current_module_id' => $quizModule->getKey(),
        'completion_percentage' => 50,
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
    ])->saveQuietly();

    $detailUrl = route('admin.courses.enrollments.show', [$course, $enrollment]);

    $this->get($detailUrl)
        ->assertOk()
        ->assertSeeText('Dettaglio iscrizione corso')
        ->assertSeeText('Corso sicurezza')
        ->assertSeeText('Mario Rossi')
        ->assertSeeText('Video introduttivo')
        ->assertSeeText('Rivedibile')
        ->assertSeeText('Quiz finale')
        ->assertSeeText('Tentativi');

    $this->getJson(route('admin.api.courses.enrollments.index', $course))
        ->assertOk()
        ->assertJsonPath('data.0.actions.detail_url', $detailUrl);

    $this->get(route('admin.users.edit', $learner).'?section=enrollments')
        ->assertOk()
        ->assertSee($detailUrl, escape: false);
});

it('lets superadmins reset quiz attempts, block or unlock modules, and reset scorm progress', function () {
    actingAsRole('superadmin');

    $learner = User::factory()->create();
    $learner->assignRole('user');

    $course = Course::factory()->create([
        'status' => 'draft',
    ]);

    $firstModule = Module::factory()->create([
        'title' => 'Introduzione',
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $quizModule = Module::factory()->create([
        'title' => 'Quiz',
        'type' => Module::TYPE_LEARNING_QUIZ,
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
        'passing_score' => 7,
        'max_score' => 10,
        'max_attempts' => 3,
    ]);
    $scormModule = Module::factory()->create([
        'title' => 'SCORM',
        'type' => Module::TYPE_SCORM,
        'order' => 3,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $lockedModule = Module::factory()->create([
        'title' => 'Follow up',
        'type' => Module::TYPE_VIDEO,
        'order' => 4,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $course->forceFill(['status' => 'published'])->saveQuietly();

    Storage::fake('local');

    $package = app(ScormService::class)->storeUploadedPackage($scormModule, scormZipUpload([
        'imsmanifest.xml' => validScormManifest(),
        'lesson/index.html' => '<html><body>SCORM lesson</body></html>',
    ]));

    $enrollment = CourseEnrollment::enroll($learner, $course);
    $firstProgress = $enrollment->moduleProgresses()->where('module_id', $firstModule->getKey())->firstOrFail();
    $quizProgress = $enrollment->moduleProgresses()->where('module_id', $quizModule->getKey())->firstOrFail();
    $scormProgress = $enrollment->moduleProgresses()->where('module_id', $scormModule->getKey())->firstOrFail();
    $lockedProgress = $enrollment->moduleProgresses()->where('module_id', $lockedModule->getKey())->firstOrFail();

    $firstProgress->forceFill([
        'status' => ModuleProgress::STATUS_COMPLETED,
        'started_at' => now()->subHour(),
        'completed_at' => now()->subMinutes(45),
    ])->saveQuietly();
    $quizProgress->forceFill([
        'status' => ModuleProgress::STATUS_FAILED,
        'started_at' => now()->subMinutes(40),
        'quiz_attempts' => 2,
        'quiz_score' => 4,
        'quiz_total_score' => 10,
    ])->saveQuietly();
    $enrollment->forceFill([
        'current_module_id' => $scormModule->getKey(),
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
    ])->saveQuietly();
    $scormProgress->forceFill([
        'status' => ModuleProgress::STATUS_AVAILABLE,
    ])->saveQuietly();
    $lockedProgress->forceFill([
        'status' => ModuleProgress::STATUS_LOCKED,
    ])->saveQuietly();

    app(ScormService::class)->initializeRuntime(
        $learner,
        $package,
        $scormProgress,
        'ITEM-DEFAULT',
        'detail-reset-session',
    );
    app(ScormService::class)->commitRuntime(
        $learner,
        $package,
        $scormProgress,
        'ITEM-DEFAULT',
        'detail-reset-session',
        [
            'cmi.core.lesson_location' => 'slide-9',
            'cmi.suspend_data' => '{"slide":9}',
            'cmi.core.lesson_status' => 'incomplete',
        ],
    );

    $this->post(route('admin.courses.enrollments.modules.reset-quiz-attempts', [$course, $enrollment, $quizModule]))
        ->assertRedirect();

    expect($quizProgress->fresh()->quiz_attempts)->toBe(0)
        ->and($quizProgress->fresh()->status)->toBe(ModuleProgress::STATUS_AVAILABLE)
        ->and((int) $enrollment->fresh()->current_module_id)->toBe((int) $quizModule->getKey());

    $this->post(route('admin.courses.enrollments.modules.block', [$course, $enrollment, $quizModule]))
        ->assertRedirect();

    expect($quizProgress->fresh()->status)->toBe(ModuleProgress::STATUS_LOCKED);

    $this->post(route('admin.courses.enrollments.modules.unlock', [$course, $enrollment, $quizModule]))
        ->assertRedirect();

    expect($quizProgress->fresh()->status)->toBe(ModuleProgress::STATUS_AVAILABLE);

    expect(ScormTracking::query()->where('course_user_id', $enrollment->getKey())->exists())->toBeTrue();

    $this->post(route('admin.courses.enrollments.modules.reset-scorm', [$course, $enrollment, $scormModule]))
        ->assertRedirect();

    expect($scormProgress->fresh()->status)->toBe(ModuleProgress::STATUS_AVAILABLE)
        ->and($lockedProgress->fresh()->status)->toBe(ModuleProgress::STATUS_LOCKED)
        ->and((int) $enrollment->fresh()->current_module_id)->toBe((int) $scormModule->getKey())
        ->and(ScormTracking::query()->where('course_user_id', $enrollment->getKey())->exists())->toBeFalse()
        ->and(ScormTrackingArchive::query()
            ->where('course_user_id', $enrollment->getKey())
            ->where('module_id', $scormModule->getKey())
            ->exists())->toBeTrue();
});
