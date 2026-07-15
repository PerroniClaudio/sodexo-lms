<?php

use App\Models\Course;
use App\Models\CourseTeacherEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    actingAsRole('admin');
});

function makeStaffUserForCourseTeacher(array $attributes = []): User
{
    return User::query()->create(array_merge([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'is_foreigner_or_immigrant' => false,
    ], $attributes));
}

it('assigns a teacher to the course even if not already enrolled', function () {
    $course = Course::factory()->create();
    $teacher = makeStaffUserForCourseTeacher();
    $teacher->assignRole('teacher');

    $response = $this->postJson(route('admin.api.courses.teacher-enrollments.store', $course), [
        'user_id' => $teacher->getKey(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Docente assegnato al corso con successo.');

    expect(CourseTeacherEnrollment::query()
        ->where('course_id', $course->getKey())
        ->where('user_id', $teacher->getKey())
        ->exists())->toBeTrue();
});

it('rejects users who do not have a teacher role', function () {
    $course = Course::factory()->create();
    $teacher = makeStaffUserForCourseTeacher();
    $teacher->assignRole('user');

    $response = $this->postJson(route('admin.api.courses.teacher-enrollments.store', $course), [
        'user_id' => $teacher->getKey(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Puoi assegnare come docente del corso solo utenti con ruolo docente.');

    expect(CourseTeacherEnrollment::query()->count())->toBe(0);
});

it('searches teachers by partial name surname or email', function () {
    $course = Course::factory()->create();
    $matchingTeacher = makeStaffUserForCourseTeacher([
        'name' => 'Giovanni',
        'surname' => 'Rossi',
        'email' => 'giovanni.rossi@example.test',
        'fiscal_code' => 'RSSGNN80A01H501Z',
    ]);
    $matchingTeacher->assignRole('teacher');

    $otherTeacher = makeStaffUserForCourseTeacher([
        'name' => 'Luca',
        'surname' => 'Bianchi',
        'email' => 'luca.bianchi@example.test',
        'fiscal_code' => 'BNCLCU80A01H501Z',
    ]);
    $otherTeacher->assignRole('teacher');

    $notTeacher = makeStaffUserForCourseTeacher([
        'name' => 'Giovanna',
        'surname' => 'Verdi',
        'email' => 'giovanna.verdi@example.test',
        'fiscal_code' => 'VRDGNN80A01H501Z',
    ]);
    $notTeacher->assignRole('user');

    $response = $this->getJson(route('admin.api.courses.teacher-enrollments.search-users', [
        'course' => $course,
        'search' => 'ross',
    ]));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingTeacher->getKey())
        ->assertJsonPath('data.0.email', 'giovanni.rossi@example.test');
});

it('restores a soft deleted course teacher assignment', function () {
    $course = Course::factory()->create();
    $teacher = makeStaffUserForCourseTeacher();
    $teacher->assignRole('teacher');

    $enrollment = CourseTeacherEnrollment::factory()->create([
        'course_id' => $course->getKey(),
        'user_id' => $teacher->getKey(),
    ]);

    $enrollment->delete();

    $response = $this->postJson(route('admin.api.courses.teacher-enrollments.store', $course), [
        'user_id' => $teacher->getKey(),
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('requires_restore', true);

    $this->postJson(route('admin.api.courses.teacher-enrollments.restore', [$course, $enrollment->getKey()]))
        ->assertOk()
        ->assertJsonPath('message', 'Assegnazione docente ripristinata con successo.');

    expect($enrollment->fresh()->trashed())->toBeFalse();
});

it('soft deletes a course teacher assignment', function () {
    $course = Course::factory()->create();
    $teacher = makeStaffUserForCourseTeacher();
    $teacher->assignRole('teacher');

    $enrollment = CourseTeacherEnrollment::factory()->create([
        'course_id' => $course->getKey(),
        'user_id' => $teacher->getKey(),
    ]);

    $this->deleteJson(route('admin.api.courses.teacher-enrollments.destroy', [$course, $enrollment]))
        ->assertOk()
        ->assertJsonPath('message', 'Docente rimosso dal corso con successo.');

    expect($enrollment->fresh()->trashed())->toBeTrue();
});
