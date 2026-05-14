<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleTutorEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
});

it('assigns selected tutors to supported modules', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
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

    expect(ModuleTutorEnrollment::query()
        ->where('module_id', $module->getKey())
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

    expect(ModuleTutorEnrollment::query()->count())->toBe(0);
});

it('returns not found when assigning tutors from unsupported modules', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'learning_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $tutor = User::factory()->create();
    $tutor->assignRole('tutor');

    $this->post(route('admin.courses.modules.tutors.assign', [$course, $module]), [
        'tutor_ids' => [$tutor->getKey()],
    ])->assertNotFound();
});

it('soft deletes a tutor assignment from a supported module', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'res',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $tutor = User::factory()->create();
    $tutor->assignRole('tutor');

    $enrollment = ModuleTutorEnrollment::factory()->create([
        'module_id' => $module->getKey(),
        'user_id' => $tutor->getKey(),
    ]);

    $response = $this->delete(route('admin.courses.modules.tutors.destroy', [$course, $module, $enrollment]));

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));
    $response->assertSessionHas('status', 'Tutor rimosso con successo.');

    expect($enrollment->fresh()->trashed())->toBeTrue();
});
