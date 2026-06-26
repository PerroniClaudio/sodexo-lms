<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\JobRole;
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

it('blocks training path enrollment creation when the user is outside the configured recipients', function () {
    $trainingPath = TrainingPath::factory()->create([
        'status' => 'published',
        'visible_to_all' => false,
        'title' => 'Percorso riservato',
    ]);
    $allowedRole = JobRole::factory()->create();
    $otherRole = JobRole::factory()->create();
    $trainingPath->jobRoles()->attach($allowedRole);

    $user = User::factory()->create([
        'job_role_id' => $otherRole->getKey(),
    ]);

    $this->postJson(route('admin.api.training-paths.enrollments.store', $trainingPath), [
        'user_id' => $user->getKey(),
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'L\'utente non rientra tra i destinatari del percorso formativo "Percorso riservato", quindi l\'iscrizione non è stata creata.');

    expect(TrainingPathEnrollment::query()->count())->toBe(0);
});

it('blocks training path enrollment creation when a linked published course is not assignable to the user', function () {
    $trainingPath = TrainingPath::factory()->create([
        'status' => 'published',
        'visible_to_all' => true,
        'title' => 'Percorso onboarding',
    ]);
    $course = Course::factory()->create([
        'status' => 'published',
        'visible_to_all' => false,
        'title' => 'Corso riservato',
    ]);
    $allowedRole = JobRole::factory()->create();
    $otherRole = JobRole::factory()->create();
    $course->jobRoles()->attach($allowedRole);
    $trainingPath->courses()->attach($course->getKey(), ['sort_order' => 1]);

    $user = User::factory()->create([
        'job_role_id' => $otherRole->getKey(),
    ]);

    $this->postJson(route('admin.api.training-paths.enrollments.store', $trainingPath), [
        'user_id' => $user->getKey(),
    ])->assertUnprocessable()
        ->assertJsonPath('errors.0', 'Il percorso contiene un corso non assegnabile: L\'utente non rientra tra i destinatari del corso "Corso riservato", quindi l\'iscrizione non è stata creata.');

    expect(TrainingPathEnrollment::query()->count())->toBe(0)
        ->and(CourseEnrollment::query()->count())->toBe(0);
});
