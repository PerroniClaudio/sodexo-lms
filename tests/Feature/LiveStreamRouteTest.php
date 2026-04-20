<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('live stream player route renders module data for the requested live module', function () {
    $user = actingAsRole('user');

    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);

    $module = Module::factory()->create([
        'title' => 'Live onboarding',
        'description' => 'Sessione live con dati reali del modulo.',
        'type' => 'live',
        'status' => 'published',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('user.live-stream.player', $module));

    $response->assertSuccessful();
    $response->assertSeeText('Live onboarding');
    $response->assertSeeText('Corso sicurezza');
    $response->assertSeeText('Sessione live con dati reali del modulo.');
});

test('user live stream route renders waiting view before the scheduled start time', function () {
    $user = actingAsRole('user');

    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);

    $module = Module::factory()->create([
        'title' => 'Live futura',
        'description' => 'Sessione live futura.',
        'type' => 'live',
        'status' => 'published',
        'appointment_start_time' => now()->addHour(),
        'appointment_end_time' => now()->addHours(2),
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('user.live-stream.player', $module));

    $response->assertSuccessful();
    $response->assertViewIs('user.live-stream.waiting');
    $response->assertSeeText('Live futura');
    $response->assertSeeText('La diretta comincia all\'orario stabilito.');
    $response->assertSeeText('Corso sicurezza');
});

test('live stream player route returns not found for non live modules', function () {
    $user = actingAsRole('user');

    $course = Course::factory()->create();

    $module = Module::factory()->create([
        'type' => 'video',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this
        ->actingAs($user)
        ->get(route('user.live-stream.player', $module))
        ->assertNotFound();
});
