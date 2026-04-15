<?php

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the create course form', function () {
    $response = $this->get(route('admin.courses.create'));

    $response->assertOk();
    $response->assertSeeText('Nuovo corso');
    $response->assertSeeText('Titolo del corso');
    $response->assertSeeText('Tipologia');
    $response->assertSeeText('Seleziona una tipologia');
    $response->assertSeeText('FAD');
    $response->assertSeeText('RES');
    $response->assertSeeText('BLENDED');
    $response->assertSeeText('FSC');
    $response->assertSeeText('FAD Asincrona');
    $response->assertSeeText('Salva e continua');
});

it('creates a draft course and redirects to the edit page', function () {
    $response = $this->post(route('admin.courses.store'), [
        'title' => 'Corso antincendio',
        'type' => 'fad',
    ]);

    $course = Course::query()->firstOrFail();

    $response->assertRedirect(route('admin.courses.edit', $course));
    $response->assertSessionHas('status', 'Corso creato con successo.');

    expect($course->title)->toBe('Corso antincendio');
    expect($course->type)->toBe('fad');
    expect($course->status)->toBe('draft');
    expect($course->expiry_date?->format('Y-m-d'))->toBe(now()->endOfYear()->format('Y-m-d'));
});

it('validates the required fields when creating a course', function () {
    $response = $this->from(route('admin.courses.create'))->post(route('admin.courses.store'), [
        'title' => '',
        'type' => '',
    ]);

    $response->assertRedirect(route('admin.courses.create'));
    $response->assertSessionHasErrors(['title', 'type']);
});

it('rejects invalid course types', function () {
    $response = $this->from(route('admin.courses.create'))->post(route('admin.courses.store'), [
        'title' => 'Corso non valido',
        'type' => 'Inventato',
    ]);

    $response->assertRedirect(route('admin.courses.create'));
    $response->assertSessionHasErrors(['type']);
});
