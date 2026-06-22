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
    $this->withoutVite();
});

it('lists training path enrollments with computed progress', function () {
    $trainingPath = TrainingPath::factory()->create();
    $courseA = Course::factory()->create();
    $courseB = Course::factory()->create();
    Module::factory()->create([
        'belongsTo' => (string) $courseB->getKey(),
        'order' => 1,
    ]);
    $trainingPath->courses()->attach($courseA, ['sort_order' => 1]);
    $trainingPath->courses()->attach($courseB, ['sort_order' => 2]);

    $user = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.test',
    ]);

    TrainingPathEnrollment::enroll($user, $trainingPath);

    CourseEnrollment::enroll($user, $courseA)->forceFill([
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completion_percentage' => 100,
        'completed_at' => now(),
    ])->save();

    CourseEnrollment::enroll($user, $courseB);

    $this->getJson(route('admin.api.training-paths.enrollments.index', $trainingPath))
        ->assertOk()
        ->assertJsonPath('data.0.user.email', 'mario@example.test')
        ->assertJsonPath('data.0.status.key', 'in_progress')
        ->assertJsonPath('data.0.completion_percentage', 50)
        ->assertJsonPath('data.0.completed_courses', 1)
        ->assertJsonPath('data.0.total_courses', 2);
});

it('creates soft deletes and restores training path enrollments', function () {
    $trainingPath = TrainingPath::factory()->create();
    $user = User::factory()->create();

    $this->postJson(route('admin.api.training-paths.enrollments.store', $trainingPath), [
        'user_id' => $user->getKey(),
    ])->assertCreated();

    $enrollment = TrainingPathEnrollment::query()->firstOrFail();

    $this->deleteJson(route('admin.api.training-paths.enrollments.destroy', [$trainingPath, $enrollment]))
        ->assertOk();

    expect($enrollment->fresh()->trashed())->toBeTrue();

    $this->postJson(route('admin.api.training-paths.enrollments.restore', [$trainingPath, $enrollment]))
        ->assertOk();

    expect($enrollment->fresh()->trashed())->toBeFalse();
});

it('asks for restore when a deleted enrollment already exists', function () {
    $trainingPath = TrainingPath::factory()->create();
    $user = User::factory()->create();
    $enrollment = TrainingPathEnrollment::factory()->create([
        'training_path_id' => $trainingPath->getKey(),
        'user_id' => $user->getKey(),
    ]);

    $enrollment->delete();

    $this->postJson(route('admin.api.training-paths.enrollments.store', $trainingPath), [
        'user_id' => $user->getKey(),
    ])->assertStatus(409)
        ->assertJsonPath('requires_restore', true);
});
