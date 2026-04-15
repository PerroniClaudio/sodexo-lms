<?php

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the edit course page with the update form and modules card', function () {
    $course = Course::factory()->create([
        'title' => 'Corso prova',
        'description' => 'Descrizione corso',
        'year' => 2026,
        'expiry_date' => now()->addMonth(),
        'status' => 'draft',
    ]);

    $response = $this->get(route('admin.courses.edit', $course));

    $response->assertOk();
    $response->assertSeeText('Modifica corso');
    $response->assertDontSeeText('Corso prova');
    $response->assertSeeText('Dati anagrafici');
    $response->assertSeeText('Moduli');
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
