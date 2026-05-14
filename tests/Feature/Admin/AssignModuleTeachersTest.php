<?php

use App\Models\Course;
use App\Models\CourseTeacherEnrollment;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
});

it('assigns selected teachers to the course from a live module modal', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'live',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $firstTeacher = User::factory()->create([
        'name' => 'Anna',
        'surname' => 'Bianchi',
    ]);
    $firstTeacher->assignRole('teacher');

    $secondTeacher = User::factory()->create([
        'name' => 'Luca',
        'surname' => 'Verdi',
    ]);
    $secondTeacher->assignRole('teacher');

    $response = $this->post(route('admin.courses.modules.teachers.assign', [$course, $module]), [
        'teacher_ids' => [
            $firstTeacher->getKey(),
            $secondTeacher->getKey(),
        ],
    ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));
    $response->assertSessionHas('status', 'Docenti assegnati con successo.');

    expect(CourseTeacherEnrollment::query()
        ->where('course_id', $course->getKey())
        ->whereIn('user_id', [$firstTeacher->getKey(), $secondTeacher->getKey()])
        ->count())->toBe(2);
});

it('rejects assigning non teacher users from the modal', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'live',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->from(route('admin.courses.modules.edit', [$course, $module]))
        ->post(route('admin.courses.modules.teachers.assign', [$course, $module]), [
            'teacher_ids' => [$user->getKey()],
        ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));
    $response->assertSessionHasErrors(['teacher_ids']);

    expect(CourseTeacherEnrollment::query()->count())->toBe(0);
});

it('returns not found when assigning teachers from a non live module', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'video',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $this->post(route('admin.courses.modules.teachers.assign', [$course, $module]), [
        'teacher_ids' => [$teacher->getKey()],
    ])->assertNotFound();
});
