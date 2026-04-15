<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

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
        'appointment_date' => Carbon::parse('2026-05-20 00:00:00'),
        'appointment_start_time' => Carbon::parse('2026-05-20 14:30:00'),
        'appointment_end_time' => Carbon::parse('2026-05-20 16:00:00'),
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.edit', [$course, $module]));

    $response->assertOk();
    $response->assertSeeText('Modifica modulo');
    $response->assertSeeText('Corso: Corso sicurezza. Tipologia: Live.');
    $response->assertSee('value="Modulo iniziale"', escape: false);
    $response->assertSeeText('Descrizione modulo');
    $response->assertSee('name="appointment_date"', escape: false);
    $response->assertSee('value="2026-05-20"', escape: false);
    $response->assertSee('name="appointment_start_time"', escape: false);
    $response->assertSee('value="14:30"', escape: false);
    $response->assertSee('name="appointment_end_time"', escape: false);
    $response->assertSee('value="16:00"', escape: false);
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
    $response->assertDontSee('name="appointment_date"', escape: false);
    $response->assertDontSee('name="appointment_start_time"', escape: false);
    $response->assertDontSee('name="appointment_end_time"', escape: false);
});
