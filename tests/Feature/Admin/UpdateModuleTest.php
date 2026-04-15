<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

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

it('updates appointment details for live modules', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'live',
        'belongsTo' => (string) $course->getKey(),
        'appointment_date' => Carbon::parse('2026-04-10 00:00:00'),
        'appointment_start_time' => Carbon::parse('2026-04-10 09:00:00'),
        'appointment_end_time' => Carbon::parse('2026-04-10 10:00:00'),
    ]);

    $response = $this->put(route('admin.courses.modules.update', [$course, $module]), [
        'title' => 'Modulo live aggiornato',
        'description' => 'Descrizione live aggiornata',
        'appointment_date' => '2026-05-20',
        'appointment_start_time' => '14:30',
        'appointment_end_time' => '16:00',
    ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));

    $module->refresh();

    expect($module->title)->toBe('Modulo live aggiornato');
    expect($module->description)->toBe('Descrizione live aggiornata');
    expect($module->appointment_date?->format('Y-m-d H:i:s'))->toBe('2026-05-20 00:00:00');
    expect($module->appointment_start_time?->format('Y-m-d H:i:s'))->toBe('2026-05-20 14:30:00');
    expect($module->appointment_end_time?->format('Y-m-d H:i:s'))->toBe('2026-05-20 16:00:00');
});

it('validates appointment details for residential modules', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'res',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->from(route('admin.courses.modules.edit', [$course, $module]))
        ->put(route('admin.courses.modules.update', [$course, $module]), [
            'title' => 'Modulo residenziale',
            'description' => 'Descrizione aggiornata',
            'appointment_date' => '2026-05-20',
            'appointment_start_time' => '16:00',
            'appointment_end_time' => '14:30',
        ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));
    $response->assertSessionHasErrors(['appointment_end_time']);
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
