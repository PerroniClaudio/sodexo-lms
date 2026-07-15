<?php

use App\Models\Course;
use App\Models\CourseFacultyMember;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    actingAsRole('admin');
});

function makeFacultyUser(array $attributes = []): User
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

it('creates a faculty member from an existing user', function () {
    $course = Course::factory()->create();
    $user = makeFacultyUser([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);

    $response = $this->postJson(route('admin.api.courses.faculty-members.store', $course), [
        'user_id' => $user->getKey(),
        'role' => CourseFacultyMember::ROLE_TEACHER,
        'affiliation' => 'Sodexo',
        'has_compensation' => true,
        'compensation_amount' => '120.50',
    ]);

    $response->assertCreated();

    expect(CourseFacultyMember::query()->where([
        'course_id' => $course->getKey(),
        'user_id' => $user->getKey(),
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'role' => CourseFacultyMember::ROLE_TEACHER,
    ])->exists())->toBeTrue();
});

it('creates a manual faculty member without a user account', function () {
    $course = Course::factory()->create();

    $this->postJson(route('admin.api.courses.faculty-members.store', $course), [
        'name' => 'Laura',
        'surname' => 'Bianchi',
        'fiscal_code' => 'BNCLRA80A01H501Z',
        'role' => CourseFacultyMember::ROLE_RPF,
        'has_compensation' => false,
    ])->assertCreated();

    expect(CourseFacultyMember::query()
        ->whereNull('user_id')
        ->where('course_id', $course->getKey())
        ->where('role', CourseFacultyMember::ROLE_RPF)
        ->exists())->toBeTrue();
});

it('allows the same user with different roles', function () {
    $course = Course::factory()->create();
    $user = makeFacultyUser();

    foreach ([CourseFacultyMember::ROLE_TEACHER, CourseFacultyMember::ROLE_MODERATOR] as $role) {
        $this->postJson(route('admin.api.courses.faculty-members.store', $course), [
            'user_id' => $user->getKey(),
            'role' => $role,
            'has_compensation' => false,
        ])->assertCreated();
    }

    expect(CourseFacultyMember::query()
        ->where('course_id', $course->getKey())
        ->where('user_id', $user->getKey())
        ->count())->toBe(2);
});

it('rejects duplicate active role for the same user', function () {
    $course = Course::factory()->create();
    $user = makeFacultyUser();

    CourseFacultyMember::factory()->create([
        'course_id' => $course->getKey(),
        'user_id' => $user->getKey(),
        'role' => CourseFacultyMember::ROLE_TEACHER,
    ]);

    $this->postJson(route('admin.api.courses.faculty-members.store', $course), [
        'user_id' => $user->getKey(),
        'role' => CourseFacultyMember::ROLE_TEACHER,
        'has_compensation' => false,
    ])->assertUnprocessable();
});

it('updates a faculty member entry', function () {
    $course = Course::factory()->create();
    $member = CourseFacultyMember::factory()->create([
        'course_id' => $course->getKey(),
        'user_id' => null,
        'name' => 'Laura',
        'surname' => 'Bianchi',
        'fiscal_code' => 'BNCLRA80A01H501Z',
        'role' => CourseFacultyMember::ROLE_RPF,
        'affiliation' => 'Vecchia affiliazione',
        'has_compensation' => false,
        'compensation_amount' => null,
    ]);

    $this->putJson(route('admin.api.courses.faculty-members.update', [$course, $member]), [
        'name' => 'Laura',
        'surname' => 'Bianchi',
        'fiscal_code' => 'BNCLRA80A01H501Z',
        'role' => CourseFacultyMember::ROLE_MODERATOR,
        'affiliation' => 'Nuova affiliazione',
        'has_compensation' => true,
        'compensation_amount' => '99.99',
    ])->assertOk();

    expect($member->fresh())
        ->role->toBe(CourseFacultyMember::ROLE_MODERATOR)
        ->affiliation->toBe('Nuova affiliazione')
        ->has_compensation->toBeTrue()
        ->compensation_amount->toBe('99.99');
});

it('validates allowed roles and compensation amount', function () {
    $course = Course::factory()->create();
    $user = makeFacultyUser();

    $this->postJson(route('admin.api.courses.faculty-members.store', $course), [
        'user_id' => $user->getKey(),
        'role' => 'invalid',
        'has_compensation' => false,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    $this->postJson(route('admin.api.courses.faculty-members.store', $course), [
        'user_id' => $user->getKey(),
        'role' => CourseFacultyMember::ROLE_TEACHER,
        'has_compensation' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['compensation_amount']);
});

it('shows the faculty section on the course edit page', function () {
    $course = Course::factory()->create();

    $this->get(route('admin.courses.edit', ['course' => $course, 'section' => 'faculty']))
        ->assertSuccessful()
        ->assertSee('Faculty')
        ->assertSee('Documenti Faculty')
        ->assertSee('Genera lettera di incarico')
        ->assertSee('Genera attestato');
});
