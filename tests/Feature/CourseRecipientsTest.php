<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\JobRole;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->withoutVite();
});

it('shows and updates course recipients', function () {
    actingAsRole('admin');

    $course = Course::factory()->create();
    $jobRole = JobRole::factory()->create(['name' => 'Cuoco']);
    $jobTask = JobTask::factory()->create(['name' => 'Preparazione']);
    $jobUnit = JobUnit::factory()->create(['name' => 'Milano']);

    $this->get(route('admin.courses.edit', [$course, 'section' => 'recipients']))
        ->assertOk()
        ->assertSeeText('Destinatari')
        ->assertSeeText('Visibile a tutti')
        ->assertSeeText('Cuoco')
        ->assertSeeText('Preparazione')
        ->assertSeeText('Milano');

    $this->put(route('admin.courses.recipients.update', $course), [
        'job_role_ids' => [$jobRole->getKey()],
        'job_task_ids' => [$jobTask->getKey()],
        'job_unit_ids' => [$jobUnit->getKey()],
    ])->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'recipients']));

    $course->refresh();

    expect($course->visible_to_all)->toBeFalse()
        ->and($course->jobRoles()->whereKey($jobRole->getKey())->exists())->toBeTrue()
        ->and($course->jobTasks()->whereKey($jobTask->getKey())->exists())->toBeTrue()
        ->and($course->jobUnits()->whereKey($jobUnit->getKey())->exists())->toBeTrue();
});

it('redirects back with an error flash when trying to update recipients for a published course', function () {
    actingAsRole('admin');

    $course = Course::factory()->create([
        'status' => 'published',
    ]);
    $jobRole = JobRole::factory()->create(['name' => 'Cuoco']);

    $response = $this
        ->from(route('admin.courses.edit', [$course, 'section' => 'recipients']))
        ->put(route('admin.courses.recipients.update', $course), [
            'job_role_ids' => [$jobRole->getKey()],
        ]);

    $response
        ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'recipients']))
        ->assertSessionHas('error', 'Non è possibile modificare i dati del corso quando è pubblicato. Solo lo stato può essere modificato.');

    expect($course->jobRoles()->whereKey($jobRole->getKey())->exists())->toBeFalse();
});

it('filters user courses by recipients', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $matchingRole = JobRole::factory()->create();
    $otherRole = JobRole::factory()->create();
    $matchingTask = JobTask::factory()->create();
    $matchingUnit = JobUnit::factory()->create();

    $user = User::factory()->create([
        'job_role_id' => $matchingRole->getKey(),
        'job_task_id' => $matchingTask->getKey(),
        'job_unit_id' => $matchingUnit->getKey(),
    ]);

    $visibleCourse = Course::factory()->create(['title' => 'Corso visibile a tutti']);
    $matchingCourse = Course::factory()->create(['title' => 'Corso compatibile', 'visible_to_all' => false]);
    $hiddenCourse = Course::factory()->create(['title' => 'Corso nascosto', 'visible_to_all' => false]);
    $emptyRestrictedCourse = Course::factory()->create(['title' => 'Corso senza destinatari', 'visible_to_all' => false]);

    $matchingCourse->jobRoles()->attach($matchingRole);
    $matchingCourse->jobTasks()->attach($matchingTask);
    $matchingCourse->jobUnits()->attach($matchingUnit);
    $hiddenCourse->jobRoles()->attach($otherRole);

    foreach ([$visibleCourse, $matchingCourse, $hiddenCourse, $emptyRestrictedCourse] as $course) {
        CourseEnrollment::enroll($user, $course);
    }

    $this->actingAs($user)
        ->get(route('user.courses.index'))
        ->assertOk()
        ->assertSeeText('Corso visibile a tutti')
        ->assertSeeText('Corso compatibile')
        ->assertDontSeeText('Corso nascosto')
        ->assertDontSeeText('Corso senza destinatari');

    $this->actingAs($user)
        ->get(route('user.courses.show', $hiddenCourse))
        ->assertForbidden();
});
