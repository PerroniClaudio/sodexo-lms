<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseTeacherEnrollment;
use App\Models\Module;
use App\Models\SatisfactionSurveyTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

function createValidSatisfactionSurveyTemplate(): SatisfactionSurveyTemplate
{
    $template = SatisfactionSurveyTemplate::query()->create([
        'is_active' => true,
        'activated_at' => now(),
    ]);

    $question = $template->questions()->create([
        'sort_order' => 1,
        'text' => 'Valutazione complessiva',
    ]);

    $question->answers()->createMany([
        ['sort_order' => 1, 'text' => 'Ottimo'],
        ['sort_order' => 2, 'text' => 'Buono'],
    ]);

    return $template;
}

it('shows the edit course page with the update form and modules card', function () {
    $course = Course::factory()->create([
        'title' => 'Corso prova',
        'type' => 'fad',
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
    $teacher = User::query()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $teacher->assignRole('teacher');
    CourseEnrollment::enroll($teacher, $course);
    CourseTeacherEnrollment::factory()->create([
        'course_id' => $course->getKey(),
        'user_id' => $teacher->getKey(),
    ]);

    $response = $this->get(route('admin.courses.edit', $course));

    $response->assertOk();
    $response->assertSeeText('Modifica corso');
    $response->assertDontSeeText('Corso prova');
    $response->assertSeeText('Tipologia: FAD');
    $response->assertSeeText('Dati anagrafici');
    $response->assertSeeText('Moduli');
    $response->assertSeeText('Nuovo modulo');
    $response->assertSeeText('Elimina corso');
    $response->assertSeeText('Elimina modulo');
    $response->assertSeeText('Aggiungi un nuovo modulo scegliendo la tipologia da creare.');
    $response->assertSeeText('Docenti del corso');
    $response->assertDontSeeText('Tutor assegnati ai moduli');
    $response->assertSeeText('Titolo del modulo');
    $response->assertSeeText('Conferma eliminazione');
    $response->assertSee('data-modules-sortable-list', escape: false);
    $response->assertSee(route('admin.courses.modules.reorder', $course), escape: false);
    $response->assertSeeText('Bozza');
    $response->assertSeeText('Pubblicato');
    $response->assertSeeText('Archiviato');
    $response->assertSeeText('Abilitazioni di rischio acquisite');
    $response->assertSeeText('Non ci sono ancora requisiti di rischio configurati.');
    $response->assertSeeText('Salva dati');
});

it('updates the course personal data', function () {
    createValidSatisfactionSurveyTemplate();

    $course = Course::factory()->create([
        'title' => 'Titolo iniziale',
        'type' => 'res',
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
        'type' => 'res',
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

it('disables the new module button when the course is published', function () {
    $course = Course::factory()->create(['status' => 'published']);

    $response = $this->get(route('admin.courses.edit', $course));

    $response->assertOk();
    $response->assertSee('data-open-module-modal', false);
    $response->assertSee('disabled', false);
    $response->assertSee('tooltip tooltip-left', false);
    $response->assertSee('data-tip="Non puoi aggiungere nuovi moduli mentre il corso è pubblicato."', false);
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
