<?php

use App\Models\Course;
use App\Models\CourseTutorEnrollment;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
});

it('assigns selected tutors to the course from a live module modal', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'live',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $firstTutor = User::factory()->create([
        'name' => 'Anna',
        'surname' => 'Bianchi',
    ]);
    $firstTutor->assignRole('tutor');

    $secondTutor = User::factory()->create([
        'name' => 'Luca',
        'surname' => 'Verdi',
    ]);
    $secondTutor->assignRole('tutor');

    $response = $this->post(route('admin.courses.modules.tutors.assign', [$course, $module]), [
        'tutor_ids' => [
            $firstTutor->getKey(),
            $secondTutor->getKey(),
        ],
    ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));
    $response->assertSessionHas('status', 'Tutor assegnati con successo.');

    expect(CourseTutorEnrollment::query()
        ->where('course_id', $course->getKey())
        ->whereIn('user_id', [$firstTutor->getKey(), $secondTutor->getKey()])
        ->count())->toBe(2);
});

it('rejects assigning non tutor users from the modal', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'live',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->from(route('admin.courses.modules.edit', [$course, $module]))
        ->post(route('admin.courses.modules.tutors.assign', [$course, $module]), [
            'tutor_ids' => [$user->getKey()],
        ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));
    $response->assertSessionHasErrors(['tutor_ids']);

    expect(CourseTutorEnrollment::query()->count())->toBe(0);
});

it('returns not found when assigning tutors from a non live module', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'video',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $tutor = User::factory()->create();
    $tutor->assignRole('tutor');

    $this->post(route('admin.courses.modules.tutors.assign', [$course, $module]), [
        'tutor_ids' => [$tutor->getKey()],
    ])->assertNotFound();
});
