<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a single active enrollment and initializes sequential module progress', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();
    $firstModule = Module::factory()->create([
        'type' => 'video',
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $secondModule = Module::factory()->create([
        'type' => 'learning_quiz',
        'order' => 2,
        'passing_score' => 7,
        'max_score' => 10,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);

    expect($enrollment->status)->toBe(CourseEnrollment::STATUS_ASSIGNED);
    expect($enrollment->current_module_id)->toBe($firstModule->getKey());
    expect($enrollment->moduleProgresses()->count())->toBe(2);
    expect($enrollment->moduleProgresses()->where('module_id', $firstModule->getKey())->value('status'))
        ->toBe(ModuleProgress::STATUS_AVAILABLE);
    expect($enrollment->moduleProgresses()->where('module_id', $secondModule->getKey())->value('status'))
        ->toBe(ModuleProgress::STATUS_LOCKED);

    expect(fn () => CourseEnrollment::enroll($user, $course))
        ->toThrow(DomainException::class);
});

it('tracks video progress and unlocks the next module only after completion', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();
    $firstModule = Module::factory()->create([
        'type' => 'video',
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $secondModule = Module::factory()->create([
        'type' => 'video',
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);
    $firstProgress = $enrollment->moduleProgresses()->where('module_id', $firstModule->getKey())->firstOrFail();
    $secondProgress = $enrollment->moduleProgresses()->where('module_id', $secondModule->getKey())->firstOrFail();

    $firstProgress->recordVideoProgress(45, 30);

    $firstProgress->refresh();
    $secondProgress->refresh();
    $enrollment->refresh();

    expect($firstProgress->status)->toBe(ModuleProgress::STATUS_IN_PROGRESS);
    expect($firstProgress->video_current_second)->toBe(45);
    expect($firstProgress->video_max_second)->toBe(45);
    expect($firstProgress->time_spent_seconds)->toBe(30);
    expect($secondProgress->status)->toBe(ModuleProgress::STATUS_LOCKED);
    expect($enrollment->current_module_id)->toBe($firstModule->getKey());

    $firstProgress->markCompleted();

    $firstProgress->refresh();
    $secondProgress->refresh();
    $enrollment->refresh();

    expect($firstProgress->status)->toBe(ModuleProgress::STATUS_COMPLETED);
    expect($secondProgress->status)->toBe(ModuleProgress::STATUS_AVAILABLE);
    expect($enrollment->current_module_id)->toBe($secondModule->getKey());
    expect($enrollment->completion_percentage)->toBe(50);
});

it('blocks quiz progression until the passing score is reached', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();
    Module::factory()->create([
        'type' => 'video',
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $quizModule = Module::factory()->create([
        'type' => 'learning_quiz',
        'order' => 2,
        'passing_score' => 7,
        'max_score' => 10,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $thirdModule = Module::factory()->create([
        'type' => 'video',
        'order' => 3,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);
    $firstProgress = $enrollment->moduleProgresses()->where('module_id', $course->modules()->orderBy('order')->value('id'))->firstOrFail();
    $firstProgress->markCompleted();

    $enrollment->refresh();

    $quizProgress = $enrollment->moduleProgresses()->where('module_id', $quizModule->getKey())->firstOrFail();
    $thirdProgress = $enrollment->moduleProgresses()->where('module_id', $thirdModule->getKey())->firstOrFail();

    $quizProgress->recordQuizAttempt(5, 10);

    $quizProgress->refresh();
    $thirdProgress->refresh();
    $enrollment->refresh();

    expect($quizProgress->status)->toBe(ModuleProgress::STATUS_FAILED);
    expect($quizProgress->quiz_attempts)->toBe(1);
    expect($quizProgress->quiz_score)->toBe(5);
    expect($quizProgress->quiz_total_score)->toBe(10);
    expect($thirdProgress->status)->toBe(ModuleProgress::STATUS_LOCKED);
    expect($enrollment->current_module_id)->toBe($quizModule->getKey());

    $quizProgress->recordQuizAttempt(8, 10);

    $quizProgress->refresh();
    $thirdProgress->refresh();
    $enrollment->refresh();

    expect($quizProgress->status)->toBe(ModuleProgress::STATUS_COMPLETED);
    expect($quizProgress->quiz_attempts)->toBe(2);
    expect($quizProgress->passed_at)->not->toBeNull();
    expect($thirdProgress->status)->toBe(ModuleProgress::STATUS_AVAILABLE);
    expect($enrollment->current_module_id)->toBe($thirdModule->getKey());
});

it('completes the enrollment on the final module and preserves history on soft delete', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'video',
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);
    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();

    $progress->markCompleted();

    $enrollment->refresh();

    expect($enrollment->status)->toBe(CourseEnrollment::STATUS_COMPLETED);
    expect($enrollment->completed_at)->not->toBeNull();
    expect($enrollment->completion_percentage)->toBe(100);
    expect($enrollment->moduleProgresses()->count())->toBe(1);

    $enrollment->delete();

    expect($enrollment->fresh()->deleted_at)->not->toBeNull();
    expect($enrollment->moduleProgresses()->count())->toBe(1);
});
