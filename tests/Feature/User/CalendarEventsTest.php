<?php

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassUser;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

test('user dashboard calendar events endpoint returns live and residential class schedules', function () {
    $user = actingAsRole('user');

    $course = Course::factory()->res()->create([
        'title' => 'Corso Sicurezza Alimentare',
    ]);

    $liveModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'title' => 'Lezione Live HACCP',
        'type' => Module::TYPE_LIVE,
    ]);

    $residentialModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'title' => 'Sessione RES Antincendio',
        'type' => Module::TYPE_RESIDENTIAL,
    ]);

    $videoModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'title' => 'Video non valido',
        'type' => Module::TYPE_VIDEO,
    ]);

    $liveClass = CourseClass::factory()->forModule($liveModule)->create([
        'name' => 'Classe Live Mattina',
    ]);

    $residentialClass = CourseClass::factory()->forModule($residentialModule)->create([
        'name' => 'Classe RES Aula 1',
    ]);

    $videoClass = CourseClass::factory()->forModule($videoModule)->create([
        'name' => 'Classe Video',
    ]);

    $liveSchedule = $liveClass->schedules()->firstOrFail();
    $liveSchedule->update([
        'starts_at' => now()->addDays(2)->setTime(9, 0),
        'ends_at' => now()->addDays(2)->setTime(11, 0),
    ]);
    $liveSchedule->refresh();

    $residentialSchedule = $residentialClass->schedules()->firstOrFail();
    $residentialSchedule->update([
        'starts_at' => now()->addDays(5)->setTime(14, 0),
        'ends_at' => now()->addDays(5)->setTime(18, 0),
    ]);
    $residentialSchedule->refresh();

    $videoClass->schedules()->firstOrFail()->update([
        'starts_at' => now()->addDay()->setTime(8, 0),
        'ends_at' => now()->addDay()->setTime(9, 0),
    ]);

    CourseClassUser::factory()->create([
        'course_class_id' => $liveClass->getKey(),
        'user_id' => $user->getKey(),
    ]);

    CourseClassUser::factory()->create([
        'course_class_id' => $residentialClass->getKey(),
        'user_id' => $user->getKey(),
    ]);

    CourseClassUser::factory()->create([
        'course_class_id' => $videoClass->getKey(),
        'user_id' => $user->getKey(),
    ]);

    $response = $this->getJson(route('user.dashboard.calendar-events'));

    $response->assertOk()
        ->assertJsonCount(2, 'events')
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('events.0', fn (AssertableJson $event) => $event
                ->where('id', 'class-'.$liveClass->getKey().'-schedule-'.$liveSchedule->getKey())
                ->where('title', 'Lezione Live HACCP')
                ->where('start', $liveSchedule->starts_at->toAtomString())
                ->where('end', $liveSchedule->ends_at->toAtomString())
                ->where('allDay', false)
                ->where('extendedProps.type', Module::TYPE_LIVE)
                ->where('extendedProps.course_title', 'Corso Sicurezza Alimentare')
                ->where('extendedProps.class_name', 'Classe Live Mattina')
                ->where('extendedProps.module_id', $liveModule->getKey())
                ->where('extendedProps.course_class_id', $liveClass->getKey())
                ->etc()
            )
            ->has('events.1', fn (AssertableJson $event) => $event
                ->where('id', 'class-'.$residentialClass->getKey().'-schedule-'.$residentialSchedule->getKey())
                ->where('title', 'Sessione RES Antincendio')
                ->where('start', $residentialSchedule->starts_at->toAtomString())
                ->where('end', $residentialSchedule->ends_at->toAtomString())
                ->where('allDay', false)
                ->where('extendedProps.type', Module::TYPE_RESIDENTIAL)
                ->where('extendedProps.course_title', 'Corso Sicurezza Alimentare')
                ->where('extendedProps.class_name', 'Classe RES Aula 1')
                ->where('extendedProps.module_id', $residentialModule->getKey())
                ->where('extendedProps.course_class_id', $residentialClass->getKey())
                ->etc()
            )
            ->etc()
        );
});

test('user dashboard calendar events endpoint requires authentication', function () {
    $this->getJson(route('user.dashboard.calendar-events'))
        ->assertUnauthorized();
});
