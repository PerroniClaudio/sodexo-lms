<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates a module', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'title' => 'Titolo iniziale',
        'description' => 'Descrizione iniziale',
        'type' => 'video',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->put(route('admin.courses.modules.update', [$course, $module]), [
        'title' => 'Titolo aggiornato',
        'description' => 'Descrizione aggiornata',
    ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));
    $response->assertSessionHas('status', 'Modulo aggiornato con successo.');

    $module->refresh();

    expect($module->title)->toBe('Titolo aggiornato');
    expect($module->description)->toBe('Descrizione aggiornata');
});

it('keeps the automatic title for quiz modules on update', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'title' => 'Titolo manuale da ignorare',
        'type' => 'learning_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->put(route('admin.courses.modules.update', [$course, $module]), [
        'description' => 'Descrizione quiz aggiornata',
    ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));

    $module->refresh();

    expect($module->title)->toBe('Quiz di apprendimento');
    expect($module->description)->toBe('Descrizione quiz aggiornata');
});
