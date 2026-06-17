<?php

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassTutor;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('tutor dashboard shows event calendar', function () {
    actingAsRole('tutor');

    $response = $this->get(route('tutor.dashboard'));

    $response->assertOk()
        ->assertSee('user-event-calendar', false)
        ->assertSee(route('tutor.dashboard.calendar-events'), false);
});

test('tutor dashboard calendar events endpoint returns assigned class events', function () {
    $tutor = actingAsRole('tutor');

    $course = Course::factory()->res()->create([
        'title' => 'Corso RES Tutor',
    ]);
    $module = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'title' => 'Modulo RES Tutor',
        'type' => Module::TYPE_RESIDENTIAL,
    ]);
    $courseClass = CourseClass::factory()->forModule($module)->create([
        'name' => 'Classe Tutor',
    ]);
    $schedule = $courseClass->schedules()->firstOrFail();
    $schedule->update([
        'starts_at' => now()->addDays(2)->setTime(9, 0),
        'ends_at' => now()->addDays(2)->setTime(12, 0),
    ]);
    $schedule->refresh();

    CourseClassTutor::factory()->create([
        'course_class_id' => $courseClass->getKey(),
        'user_id' => $tutor->getKey(),
    ]);

    $response = $this->getJson(route('tutor.dashboard.calendar-events'));

    $response->assertOk()
        ->assertJsonCount(1, 'events')
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('events.0', fn (AssertableJson $event) => $event
                ->where('id', 'tutor-class-'.$courseClass->getKey().'-schedule-'.$schedule->getKey())
                ->where('title', 'Modulo RES Tutor')
                ->where('start', $schedule->starts_at->toAtomString())
                ->where('end', $schedule->ends_at->toAtomString())
                ->where('extendedProps.course_title', 'Corso RES Tutor')
                ->where('extendedProps.course_url', route('tutor.courses.show', $course))
                ->where('extendedProps.class_name', 'Classe Tutor')
                ->etc()
            )
            ->etc()
        );
});
