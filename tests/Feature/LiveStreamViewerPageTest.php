<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Support\Carbon;

test('viewer page renders fullscreen toggle for teacher feed', function () {
    $course = new Course([
        'title' => 'Corso sicurezza',
    ]);

    $module = new Module([
        'title' => 'Live onboarding',
        'description' => 'Sessione live con dati reali del modulo.',
        'appointment_start_time' => Carbon::parse('2026-05-07 10:00:00'),
        'appointment_end_time' => Carbon::parse('2026-05-07 11:00:00'),
    ]);

    $module->setRelation('course', $course);

    $liveStreamConfig = [
        'role' => 'user',
        'streamMode' => 'teacher',
        'capabilities' => [
            'canRaiseHand' => true,
            'canModerateChat' => false,
        ],
    ];

    $this->view('user.live-stream.player', compact('course', 'module', 'liveStreamConfig'))
        ->assertSee('data-live-stream-main-stage-shell', false)
        ->assertSee('data-live-stream-fullscreen-toggle', false)
        ->assertSeeText('Schermo intero')
        ->assertSeeText('Docente non connesso');
});
