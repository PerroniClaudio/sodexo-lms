<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\JobRole;
use App\Models\Module;
use App\Models\TrainingPath;
use App\Models\TrainingPathCourseApproval;
use App\Models\TrainingPathEnrollment;
use App\Models\User;

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('lists training path enrollments with computed progress', function () {
    $trainingPath = TrainingPath::factory()->create(['status' => 'published']);
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
    $trainingPath = TrainingPath::factory()->create(['status' => 'published']);
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
    $trainingPath = TrainingPath::factory()->create(['status' => 'published']);
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

it('requires approval then enrolls every course while skipping the ineligible course in the path', function () {
    $trainingPath = TrainingPath::factory()->create([
        'status' => 'published',
        'visible_to_all' => true,
        'title' => 'Percorso onboarding',
    ]);
    $course = Course::factory()->create([
        'status' => 'draft',
        'visible_to_all' => false,
        'title' => 'Corso riservato',
    ]);
    $allowedRole = JobRole::factory()->create();
    $otherRole = JobRole::factory()->create();
    $course->jobRoles()->attach($allowedRole);
    Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'order' => 1,
    ]);
    Course::withoutEvents(fn () => $course->update(['status' => 'published']));
    $trainingPath->courses()->attach($course->getKey(), ['sort_order' => 1]);
    $eligibleCourse = Course::factory()->create([
        'status' => 'draft',
        'title' => 'Corso successivo',
        'visible_to_all' => true,
    ]);
    Module::factory()->create([
        'belongsTo' => (string) $eligibleCourse->getKey(),
        'order' => 1,
    ]);
    Course::withoutEvents(fn () => $eligibleCourse->update(['status' => 'published']));
    $trainingPath->courses()->attach($eligibleCourse->getKey(), ['sort_order' => 2]);

    $user = User::factory()->create([
        'job_role_id' => $otherRole->getKey(),
    ]);

    $this->postJson(route('admin.api.training-paths.enrollments.store', $trainingPath), [
        'user_id' => $user->getKey(),
    ])->assertUnprocessable()
        ->assertJsonPath('requires_approval', true)
        ->assertJsonPath('issues.0.course_id', $course->getKey());

    expect(TrainingPathEnrollment::query()->count())->toBe(0)
        ->and(CourseEnrollment::query()->count())->toBe(0);

    $this->postJson(route('admin.api.training-paths.enrollments.store', $trainingPath), [
        'user_id' => $user->getKey(),
        'approve_ineligible_courses' => true,
    ])->assertCreated();

    expect(CourseEnrollment::query()->where('user_id', $user->getKey())->count())->toBe(2)
        ->and(TrainingPathCourseApproval::query()->where('status', TrainingPathCourseApproval::STATUS_APPROVED)->count())->toBe(1)
        ->and(TrainingPathEnrollment::query()->sole()->current_course_id)->toBe($eligibleCourse->getKey());

    $this->getJson(route('admin.api.training-paths.enrollments.index', $trainingPath))
        ->assertOk()
        ->assertJsonPath('data.0.completed_courses', 0)
        ->assertJsonPath('data.0.total_courses', 1);

    $this->actingAs($user)->withSession(['active_role' => 'user']);

    $this->get(route('user.training-paths.show', TrainingPathEnrollment::query()->sole()))
        ->assertOk()
        ->assertSeeText('Saltato per approvazione')
        ->assertSeeText('0/1 corsi completati');
});

it('shows approval logs only to superadmins in tools', function () {
    $approval = TrainingPathCourseApproval::factory()->create([
        'reasons' => ['Destinatari del corso non compatibili.'],
    ]);

    $this->get(route('admin.tools.training-path-approvals.index'))->assertRedirect();

    auth()->user()->assignRole('superadmin');
    $this->withSession(['active_role' => 'superadmin']);

    $this->get(route('admin.tools.training-path-approvals.index'))
        ->assertOk()
        ->assertSeeText($approval->course->title)
        ->assertSeeText('Destinatari del corso non compatibili.');
});
