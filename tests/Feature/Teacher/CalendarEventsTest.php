<?php

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassTeacher;
use App\Models\Module;
use App\Models\ModuleTeacherEnrollment;
use Illuminate\Testing\Fluent\AssertableJson;

test('teacher dashboard calendar events endpoint returns residential and async module events', function () {
    $teacher = actingAsRole('teacher');

    $residentialCourse = Course::factory()->res()->create([
        'title' => 'Corso RES Docente',
    ]);
    $asyncCourse = Course::factory()->async()->create([
        'title' => 'Corso FAD Asincrona Docente',
    ]);
    $fadCourse = Course::factory()->create([
        'type' => 'fad',
        'title' => 'Corso FAD da ignorare',
    ]);

    $residentialModule = Module::factory()->create([
        'belongsTo' => (string) $residentialCourse->getKey(),
        'title' => 'Modulo RES Docente',
        'type' => Module::TYPE_RESIDENTIAL,
    ]);
    $asyncModule = Module::factory()->create([
        'belongsTo' => (string) $asyncCourse->getKey(),
        'title' => 'Modulo Async Docente',
        'type' => Module::TYPE_SCORM,
        'appointment_start_time' => now()->addDays(4)->setTime(15, 0),
        'appointment_end_time' => now()->addDays(4)->setTime(16, 30),
    ]);
    $ignoredLiveModule = Module::factory()->create([
        'belongsTo' => (string) $residentialCourse->getKey(),
        'title' => 'Modulo Live da ignorare',
        'type' => Module::TYPE_LIVE,
    ]);
    $ignoredScormModule = Module::factory()->create([
        'belongsTo' => (string) $fadCourse->getKey(),
        'title' => 'SCORM da ignorare',
        'type' => Module::TYPE_SCORM,
        'appointment_start_time' => now()->addDays(5)->setTime(10, 0),
        'appointment_end_time' => now()->addDays(5)->setTime(11, 0),
    ]);

    $residentialClass = CourseClass::factory()->forModule($residentialModule)->create([
        'name' => 'Classe RES Docente',
    ]);

    $residentialSchedule = $residentialClass->schedules()->firstOrFail();
    $residentialSchedule->update([
        'starts_at' => now()->addDays(2)->setTime(9, 0),
        'ends_at' => now()->addDays(2)->setTime(12, 0),
    ]);
    $residentialSchedule->refresh();

    CourseClassTeacher::factory()->create([
        'course_class_id' => $residentialClass->getKey(),
        'user_id' => $teacher->getKey(),
    ]);

    ModuleTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'module_id' => $asyncModule->getKey(),
    ]);
    ModuleTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'module_id' => $ignoredLiveModule->getKey(),
    ]);
    ModuleTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'module_id' => $ignoredScormModule->getKey(),
    ]);

    $response = $this->getJson(route('teacher.dashboard.calendar-events'));

    $response->assertOk()
        ->assertJsonCount(2, 'events')
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('events.0', fn (AssertableJson $event) => $event
                ->where('id', 'teacher-class-'.$residentialClass->getKey().'-schedule-'.$residentialSchedule->getKey())
                ->where('title', 'Modulo RES Docente')
                ->where('start', $residentialSchedule->starts_at->toAtomString())
                ->where('end', $residentialSchedule->ends_at->toAtomString())
                ->where('allDay', false)
                ->where('extendedProps.type', Module::TYPE_RESIDENTIAL)
                ->where('extendedProps.course_title', 'Corso RES Docente')
                ->where('extendedProps.course_type', 'res')
                ->where('extendedProps.class_name', 'Classe RES Docente')
                ->where('extendedProps.module_id', $residentialModule->getKey())
                ->where('extendedProps.course_class_id', $residentialClass->getKey())
                ->etc()
            )
            ->has('events.1', fn (AssertableJson $event) => $event
                ->where('id', 'teacher-module-'.$asyncModule->getKey())
                ->where('title', 'Modulo Async Docente')
                ->where('start', $asyncModule->appointment_start_time?->toAtomString())
                ->where('end', $asyncModule->appointment_end_time?->toAtomString())
                ->where('allDay', false)
                ->where('extendedProps.type', 'async')
                ->where('extendedProps.course_title', 'Corso FAD Asincrona Docente')
                ->where('extendedProps.course_type', 'async')
                ->where('extendedProps.class_name', 'FAD Asincrona')
                ->where('extendedProps.module_id', $asyncModule->getKey())
                ->where('extendedProps.course_class_id', null)
                ->etc()
            )
            ->etc()
        );
});

test('teacher dashboard calendar events endpoint requires authentication', function () {
    $this->getJson(route('teacher.dashboard.calendar-events'))
        ->assertUnauthorized();
});

test('teacher fake calendar events endpoint keeps asynchronous events consistent', function () {
    actingAsRole('teacher');

    $response = $this->getJson(route('teacher.dashboard.calendar-events.fake'));

    $response->assertOk()
        ->assertJsonCount(5, 'events')
        ->assertJsonPath('events.0.extendedProps.type', 'async')
        ->assertJsonPath('events.0.extendedProps.course_type', 'async')
        ->assertJsonPath('events.0.extendedProps.class_name', 'FAD Asincrona')
        ->assertJsonPath('events.1.extendedProps.type', 'async')
        ->assertJsonPath('events.1.extendedProps.course_type', 'async')
        ->assertJsonPath('events.1.extendedProps.class_name', 'FAD Asincrona')
        ->assertJsonMissingPath('events.0.extendedProps.type.live')
        ->assertJsonMissing(['extendedProps' => ['type' => Module::TYPE_LIVE]]);
});
