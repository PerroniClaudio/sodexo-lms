<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
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
    $response->assertSeeText('Titolo del modulo');
    $response->assertSeeText('Conferma eliminazione');
    $response->assertSee('data-modules-sortable-list', escape: false);
    $response->assertSee(route('admin.courses.modules.reorder', $course), escape: false);
    $response->assertSeeText('Bozza');
    $response->assertSeeText('Pubblicato');
    $response->assertSeeText('Archiviato');
    $response->assertSeeText('Salva dati');
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
    ]);

    $response->assertRedirect(route('admin.courses.edit', $course));
    $response->assertSessionHas('status', 'Corso aggiornato con successo.');

    $course->refresh();

    expect($course->title)->toBe('Titolo aggiornato');
    expect($course->description)->toBe('Descrizione aggiornata');
    expect($course->year)->toBe(2027);
    expect($course->expiry_date?->format('Y-m-d'))->toBe('2027-12-31');
    expect($course->status)->toBe('published');
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
