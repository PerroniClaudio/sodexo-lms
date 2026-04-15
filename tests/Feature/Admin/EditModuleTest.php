<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the edit module page', function () {
    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);
    $module = Module::factory()->create([
        'title' => 'Modulo iniziale',
        'description' => 'Descrizione modulo',
        'type' => 'live',
        'status' => 'draft',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.edit', [$course, $module]));

    $response->assertOk();
    $response->assertSeeText('Modifica modulo');
    $response->assertSeeText('Corso: Corso sicurezza. Tipologia: Live.');
    $response->assertSee('value="Modulo iniziale"', escape: false);
    $response->assertSeeText('Descrizione modulo');
    $response->assertSeeText('Salva modulo');
});

it('does not show the editable title field for quiz modules', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'title' => 'Quiz di gradimento',
        'type' => 'satisfaction_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.edit', [$course, $module]));

    $response->assertOk();
    $response->assertDontSee('name="title"', escape: false);
});
