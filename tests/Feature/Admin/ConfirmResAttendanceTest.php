<?php

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassSchedule;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

function confirmResAttendanceModule(Course $course, int $order, string $title = 'Modulo RES'): Module
{
    return Module::factory()->create([
        'title' => $title,
        'type' => Module::TYPE_RESIDENTIAL,
        'order' => $order,
        'belongsTo' => (string) $course->getKey(),
    ]);
}

function confirmResClassSchedule(Module $module, string $startsAt, string $endsAt): void
{
    $courseClass = CourseClass::factory()->forModule($module)->create();

    CourseClassSchedule::factory()
        ->forCourseClass($courseClass)
        ->create([
            'starts_at' => Carbon::parse($startsAt),
            'ends_at' => Carbon::parse($endsAt),
        ]);
}

function confirmResAttendanceRecord(Course $course, User $user, string $type, string $recordedAt): void
{
    DB::table('course_attendance_records')->insert([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'type' => $type,
        'session_id' => (string) Str::uuid(),
        'created_by_user_id' => auth()->id(),
        'recorded_at' => $recordedAt,
    ]);
}

it('shows confirmation controls for res and blended attendees only', function (string $type, bool $shouldSeeButton) {
    $course = Course::factory()->create(['type' => $type]);

    if ($shouldSeeButton) {
        $module = confirmResAttendanceModule($course, 1);
        $user = User::factory()->create();
        CourseEnrollment::enroll($user, $course);
        confirmResClassSchedule($module, '2026-06-01 10:00:00', '2026-06-01 12:00:00');
        confirmResAttendanceRecord($course, $user, 'entry', '2026-06-01 10:00:00');
        confirmResAttendanceRecord($course, $user, 'exit', '2026-06-01 12:00:00');
    }

    $response = $this->get(route('admin.courses.edit', [$course, 'section' => 'attendees']));

    $response->assertOk();

    if ($shouldSeeButton) {
        $response
            ->assertSeeText('Conferma presenti')
            ->assertSee('name="minimum_attendance_percentage"', false)
            ->assertSee('value="90"', false);
    } else {
        $response->assertDontSeeText('Conferma presenti');
    }
})->with([
    'res' => ['res', true],
    'blended' => ['blended', true],
    'fad' => ['fad', false],
]);

it('requires a residential module for blended confirmations', function () {
    $course = Course::factory()->create(['type' => 'blended']);
    confirmResAttendanceModule($course, 1, 'RES sicurezza');

    $this->from(route('admin.courses.edit', [$course, 'section' => 'attendees']))
        ->post(route('admin.courses.attendance.confirm', $course), [
            'minimum_attendance_percentage' => 90,
        ])
        ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'attendees']))
        ->assertSessionHasErrors('module_id');

    $this->get(route('admin.courses.edit', [$course, 'section' => 'attendees']))
        ->assertOk()
        ->assertSeeText('RES sicurezza')
        ->assertSee('name="module_id"', false);
});

it('rejects non residential or foreign modules', function () {
    $course = Course::factory()->create(['type' => 'res']);
    $foreignCourse = Course::factory()->create(['type' => 'res']);
    $videoModule = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $foreignModule = confirmResAttendanceModule($foreignCourse, 1);

    foreach ([$videoModule, $foreignModule] as $module) {
        $this->from(route('admin.courses.edit', [$course, 'section' => 'attendees']))
            ->post(route('admin.courses.attendance.confirm', $course), [
                'module_id' => $module->getKey(),
                'minimum_attendance_percentage' => 90,
            ])
            ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'attendees']))
            ->assertSessionHasErrors('module_id');
    }
});

it('confirms only users above threshold for the selected residential module', function () {
    $course = Course::factory()->create(['type' => 'res']);
    $resModule = confirmResAttendanceModule($course, 1);
    $otherResModule = confirmResAttendanceModule($course, 2);
    $nextModule = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'order' => 3,
        'belongsTo' => (string) $course->getKey(),
    ]);
    confirmResClassSchedule($resModule, '2026-06-01 10:00:00', '2026-06-01 12:00:00');
    confirmResClassSchedule($resModule, '2026-07-01 10:00:00', '2026-07-01 12:00:00');

    $qualifiedUser = User::factory()->create();
    $nonQualifiedUser = User::factory()->create();
    $qualifiedEnrollment = CourseEnrollment::enroll($qualifiedUser, $course);
    $nonQualifiedEnrollment = CourseEnrollment::enroll($nonQualifiedUser, $course);

    confirmResAttendanceRecord($course, $qualifiedUser, 'entry', '2026-06-01 09:30:00');
    confirmResAttendanceRecord($course, $qualifiedUser, 'exit', '2026-06-01 11:50:00');
    confirmResAttendanceRecord($course, $qualifiedUser, 'entry', '2026-07-01 10:00:00');
    confirmResAttendanceRecord($course, $qualifiedUser, 'exit', '2026-07-01 12:00:00');
    confirmResAttendanceRecord($course, $nonQualifiedUser, 'entry', '2026-06-01 10:00:00');
    confirmResAttendanceRecord($course, $nonQualifiedUser, 'exit', '2026-06-01 10:30:00');

    Carbon::setTestNow('2026-06-15 12:00:00');

    $this->post(route('admin.courses.attendance.confirm', $course), [
        'module_id' => $resModule->getKey(),
        'minimum_attendance_percentage' => 90,
    ])
        ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'attendees']))
        ->assertSessionHas('status', 'Presenze confermate. 1 utenti abilitati, 0 già completati, 0 sopra soglia ma non ancora sul modulo corrente.');

    Carbon::setTestNow();

    $qualifiedEnrollment->refresh();
    $nonQualifiedEnrollment->refresh();

    expect($qualifiedEnrollment->moduleProgresses()->where('module_id', $resModule->getKey())->first()->status)->toBe(ModuleProgress::STATUS_COMPLETED)
        ->and($qualifiedEnrollment->moduleProgresses()->where('module_id', $otherResModule->getKey())->first()->status)->toBe(ModuleProgress::STATUS_AVAILABLE)
        ->and($qualifiedEnrollment->current_module_id)->toBe($otherResModule->getKey())
        ->and($nonQualifiedEnrollment->moduleProgresses()->where('module_id', $resModule->getKey())->first()->status)->toBe(ModuleProgress::STATUS_AVAILABLE)
        ->and($nonQualifiedEnrollment->moduleProgresses()->where('module_id', $nextModule->getKey())->first()->status)->toBe(ModuleProgress::STATUS_LOCKED);
});

it('does not force completion when the selected module is not current', function () {
    $course = Course::factory()->create(['type' => 'blended']);
    $firstModule = confirmResAttendanceModule($course, 1, 'RES iniziale');
    $selectedModule = confirmResAttendanceModule($course, 2, 'RES successivo');
    confirmResClassSchedule($selectedModule, '2026-06-01 10:00:00', '2026-06-01 12:00:00');

    $user = User::factory()->create();
    $enrollment = CourseEnrollment::enroll($user, $course);
    confirmResAttendanceRecord($course, $user, 'entry', '2026-06-01 10:00:00');
    confirmResAttendanceRecord($course, $user, 'exit', '2026-06-01 12:00:00');

    Carbon::setTestNow('2026-06-15 12:00:00');

    $this->post(route('admin.courses.attendance.confirm', $course), [
        'module_id' => $selectedModule->getKey(),
        'minimum_attendance_percentage' => 90,
    ])
        ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'attendees']))
        ->assertSessionHas('status', 'Presenze confermate. 0 utenti abilitati, 0 già completati, 1 sopra soglia ma non ancora sul modulo corrente.');

    Carbon::setTestNow();

    $enrollment->refresh();

    expect($enrollment->current_module_id)->toBe($firstModule->getKey())
        ->and($enrollment->moduleProgresses()->where('module_id', $selectedModule->getKey())->first()->status)->toBe(ModuleProgress::STATUS_LOCKED);
});
