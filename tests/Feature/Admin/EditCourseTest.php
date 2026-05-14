<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleTeacherEnrollment;
use App\Models\ModuleTutorEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('shows the edit course page with the update form and modules card', function () {
    $course = Course::factory()->create([
        'title' => 'Corso prova',
        'description' => 'Descrizione corso',
        'year' => 2026,
        'expiry_date' => now()->addMonth(),
        'status' => 'draft',
    ]);
    Module::factory()->create([
        'title' => 'Modulo prova',
        'type' => 'video',
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $teacher = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.test',
    ]);
    $teacher->assignRole('teacher');
    ModuleTeacherEnrollment::factory()->create([
        'module_id' => $course->modules()->first()->getKey(),
        'user_id' => $teacher->getKey(),
    ]);
    $tutor = User::factory()->create([
        'name' => 'Paolo',
        'surname' => 'Blu',
        'email' => 'paolo.blu@example.test',
    ]);
    $tutor->assignRole('tutor');
    ModuleTutorEnrollment::factory()->create([
        'module_id' => $course->modules()->first()->getKey(),
        'user_id' => $tutor->getKey(),
    ]);

    $response = $this->get(route('admin.courses.edit', $course));

    $response->assertOk();
    $response->assertSeeText('Modifica corso');
    $response->assertDontSeeText('Corso prova');
    $response->assertSeeText('Dati anagrafici');
    $response->assertSeeText('Moduli');
    $response->assertSeeText('Nuovo modulo');
    $response->assertSeeText('Elimina corso');
    $response->assertSeeText('Elimina modulo');
    $response->assertSeeText('Aggiungi un nuovo modulo scegliendo la tipologia da creare.');
    $response->assertSeeText('Docenti assegnati ai moduli');
    $response->assertSeeText('Mario Rossi');
    $response->assertSeeText('mario.rossi@example.test');
    $response->assertSeeText('Tutor assegnati ai moduli');
    $response->assertSeeText('Paolo Blu');
    $response->assertSeeText('paolo.blu@example.test');
    $response->assertSeeText('Moduli assegnati');
    $response->assertSeeText('Titolo del modulo');
    $response->assertSeeText('Conferma eliminazione');
    $response->assertSee('data-modules-sortable-list', escape: false);
    $response->assertSee(route('admin.courses.modules.reorder', $course), escape: false);
    $response->assertSeeText('Bozza');
    $response->assertSeeText('Pubblicato');
    $response->assertSeeText('Archiviato');
    $response->assertSeeText('Salva dati');

    $assignedTeachers = $response->viewData('assignedTeachers');
    $assignedTutors = $response->viewData('assignedTutors');

    expect($assignedTeachers)->toHaveCount(1);
    expect($assignedTeachers->first()->module_enrollments_count)->toBe(1);
    expect($assignedTutors)->toHaveCount(1);
    expect($assignedTutors->first()->module_enrollments_count)->toBe(1);
});

it('aggregates assigned teachers by course modules', function () {
    $course = Course::factory()->create();
    $firstModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
    ]);
    $secondModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
    ]);

    $teacher = User::factory()->create([
        'name' => 'Giulia',
        'surname' => 'Neri',
        'email' => 'giulia.neri@example.test',
    ]);
    $teacher->assignRole('teacher');

    ModuleTeacherEnrollment::factory()->create([
        'module_id' => $firstModule->getKey(),
        'user_id' => $teacher->getKey(),
    ]);
    ModuleTeacherEnrollment::factory()->create([
        'module_id' => $secondModule->getKey(),
        'user_id' => $teacher->getKey(),
    ]);

    $response = $this->get(route('admin.courses.edit', $course));

    $response->assertOk();
    $response->assertSeeText('Giulia Neri');
    $response->assertSeeText('giulia.neri@example.test');

    $assignedTeachers = $response->viewData('assignedTeachers');

    expect($assignedTeachers)->toHaveCount(1);
    expect((int) $assignedTeachers->first()->module_enrollments_count)->toBe(2);
});

it('aggregates assigned tutors by course modules', function () {
    $course = Course::factory()->create();
    $firstModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
    ]);
    $secondModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
    ]);

    $tutor = User::factory()->create([
        'name' => 'Sara',
        'surname' => 'Gialli',
        'email' => 'sara.gialli@example.test',
    ]);
    $tutor->assignRole('tutor');

    ModuleTutorEnrollment::factory()->create([
        'module_id' => $firstModule->getKey(),
        'user_id' => $tutor->getKey(),
    ]);
    ModuleTutorEnrollment::factory()->create([
        'module_id' => $secondModule->getKey(),
        'user_id' => $tutor->getKey(),
    ]);

    $response = $this->get(route('admin.courses.edit', $course));

    $response->assertOk();
    $response->assertSeeText('Sara Gialli');
    $response->assertSeeText('sara.gialli@example.test');

    $assignedTutors = $response->viewData('assignedTutors');

    expect($assignedTutors)->toHaveCount(1);
    expect((int) $assignedTutors->first()->module_enrollments_count)->toBe(2);
});

it('updates the course personal data', function () {
    $course = Course::factory()->create([
        'title' => 'Titolo iniziale',
        'description' => 'Descrizione iniziale',
        'year' => 2025,
        'expiry_date' => now()->addDays(10),
        'status' => 'draft',
    ]);

    $response = $this->put(route('admin.courses.update', $course), [
        'title' => 'Titolo aggiornato',
        'description' => 'Descrizione aggiornata',
        'year' => 2027,
        'expiry_date' => '2027-12-31',
        'status' => 'published',
        'has_satisfaction_survey' => '1',
        'satisfaction_survey_required_for_certificate' => '1',
    ]);

    $response->assertRedirect(route('admin.courses.edit', $course));
    $response->assertSessionHas('status', 'Corso aggiornato con successo.');

    $course->refresh();

    expect($course->title)->toBe('Titolo aggiornato');
    expect($course->description)->toBe('Descrizione aggiornata');
    expect($course->year)->toBe(2027);
    expect($course->expiry_date?->format('Y-m-d'))->toBe('2027-12-31');
    expect($course->status)->toBe('published');
    expect($course->has_satisfaction_survey)->toBeTrue();
    expect($course->satisfaction_survey_required_for_certificate)->toBeTrue();
    expect($course->satisfactionModule()?->type)->toBe(Module::TYPE_SATISFACTION_QUIZ);
    expect($course->satisfactionModule()?->order)->toBe(1);
});

it('allows changing only the status for a published course when other form values are unchanged', function () {
    $course = Course::factory()->create([
        'title' => 'Titolo pubblicato',
        'description' => 'Descrizione pubblicata',
        'year' => 2026,
        'expiry_date' => now()->setDate(2027, 12, 31)->setTime(15, 45),
        'status' => 'published',
        'has_satisfaction_survey' => true,
        'satisfaction_survey_required_for_certificate' => false,
    ]);

    $response = $this->put(route('admin.courses.update', $course), [
        'title' => 'Titolo pubblicato',
        'description' => 'Descrizione pubblicata',
        'year' => 2026,
        'expiry_date' => '2027-12-31',
        'status' => 'archived',
        'has_satisfaction_survey' => '1',
    ]);

    $response->assertRedirect(route('admin.courses.edit', $course));
    $response->assertSessionHas('status', 'Corso aggiornato con successo.');

    $course->refresh();

    expect($course->status)->toBe('archived');
    expect($course->title)->toBe('Titolo pubblicato');
    expect($course->description)->toBe('Descrizione pubblicata');
    expect($course->year)->toBe(2026);
    expect($course->expiry_date?->format('Y-m-d'))->toBe('2027-12-31');
    expect($course->has_satisfaction_survey)->toBeTrue();
    expect($course->satisfaction_survey_required_for_certificate)->toBeFalse();
});

it('soft deletes a course', function () {
    $course = Course::factory()->create();

    $response = $this->delete(route('admin.courses.destroy', $course));

    $response->assertRedirect(route('admin.courses.index'));
    $response->assertSessionHas('status', 'Corso eliminato con successo.');

    expect(Course::find($course->id))->toBeNull();
    expect(Course::withTrashed()->find($course->id)?->trashed())->toBeTrue();
});

it('soft deletes a module from the course edit page', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->delete(route('admin.courses.modules.destroy', [$course, $module]));

    $response->assertRedirect(route('admin.courses.edit', $course));
    $response->assertSessionHas('status', 'Modulo eliminato con successo.');

    expect(Module::find($module->id))->toBeNull();
    expect(Module::withTrashed()->find($module->id)?->trashed())->toBeTrue();
});
