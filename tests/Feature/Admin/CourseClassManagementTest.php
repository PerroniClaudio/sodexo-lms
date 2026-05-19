<?php

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassTeacher;
use App\Models\CourseClassUser;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleTeacherEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
});

it('shows the classes section only for supported scheduled modules', function (string $courseType, string $moduleType, bool $visible) {
    $course = Course::factory()->create(['type' => $courseType]);
    $module = Module::factory()->create([
        'type' => $moduleType,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.edit', [$course, $module]));

    $response->assertOk();

    if ($visible) {
        $response->assertSeeText('Nuova classe');
    } else {
        $response->assertDontSeeText('Nuova classe');
    }
})->with([
    'res live' => ['res', 'live', true],
    'res residential' => ['res', 'res', true],
    'async live' => ['async', 'live', true],
    'fad live' => ['fad', 'live', false],
    'res video' => ['res', 'video', false],
]);

it('creates a course class with valid dates', function () {
    $course = Course::factory()->res()->create();
    $module = Module::factory()->create(['type' => 'res', 'belongsTo' => (string) $course->getKey()]);

    $this->postJson(route('admin.courses.classes.store', $course), classPayload($module))->assertCreated();

    expect(CourseClass::query()->where('module_id', $module->getKey())->where('name', 'Classe A')->exists())->toBeTrue();
});

it('creates a class with multiple schedules', function () {
    $course = Course::factory()->res()->create();
    $module = Module::factory()->create(['type' => 'live', 'belongsTo' => (string) $course->getKey()]);

    $this->postJson(route('admin.courses.classes.store', $course), classPayload($module, [
        'schedules' => [
            [
                'starts_at_date' => '2026-06-01',
                'starts_at_time' => '09:00',
                'ends_at_date' => '2026-06-01',
                'ends_at_time' => '11:00',
            ],
            [
                'starts_at_date' => '2026-06-02',
                'starts_at_time' => '14:00',
                'ends_at_date' => '2026-06-02',
                'ends_at_time' => '16:00',
            ],
        ],
    ]))->assertCreated();

    $courseClass = CourseClass::query()->where('module_id', $module->getKey())->firstOrFail();

    expect($courseClass->schedules()->count())->toBe(2);
});

it('rejects invalid class date ranges and unsupported courses', function () {
    $course = Course::factory()->res()->create();
    $unsupportedCourse = Course::factory()->create(['type' => 'fad']);
    $module = Module::factory()->create(['type' => 'res', 'belongsTo' => (string) $course->getKey()]);
    $unsupportedModule = Module::factory()->create(['type' => 'res', 'belongsTo' => (string) $unsupportedCourse->getKey()]);

    $this->postJson(route('admin.courses.classes.store', $course), classPayload($module, [
        'schedules' => [[
            'starts_at_date' => '2026-06-01',
            'starts_at_time' => '13:00',
            'ends_at_date' => '2026-06-01',
            'ends_at_time' => '09:00',
        ]],
    ]))->assertUnprocessable();

    $this->postJson(route('admin.courses.classes.store', $unsupportedCourse), classPayload($unsupportedModule))->assertForbidden();
});

it('assigns standard users and auto enrolls them into the course', function () {
    $course = Course::factory()->res()->create();
    $courseClass = CourseClass::factory()->forCourse($course)->create();
    $user = createClassUser();

    $this->postJson(route('admin.courses.classes.users.store', [$course, $courseClass]), [
        'user_ids' => [$user->getKey()],
    ])->assertCreated();

    expect(CourseClassUser::query()->where('course_class_id', $courseClass->getKey())->where('user_id', $user->getKey())->exists())->toBeTrue()
        ->and(CourseEnrollment::query()->where('course_id', $course->getKey())->where('user_id', $user->getKey())->exists())->toBeTrue();
});

it('assigns multiple standard users in a single request', function () {
    $course = Course::factory()->res()->create();
    $courseClass = CourseClass::factory()->forCourse($course)->create();
    $users = collect(range(1, 3))->map(fn (): User => createClassUser());

    $this->postJson(route('admin.courses.classes.users.store', [$course, $courseClass]), [
        'user_ids' => $users->pluck('id')->all(),
    ])->assertCreated();

    $users->each(function (User $user) use ($course, $courseClass): void {
        expect(CourseClassUser::query()->where('course_class_id', $courseClass->getKey())->where('user_id', $user->getKey())->exists())->toBeTrue()
            ->and(CourseEnrollment::query()->where('course_id', $course->getKey())->where('user_id', $user->getKey())->exists())->toBeTrue();
    });
});

it('removes multiple standard users in a single request', function () {
    $course = Course::factory()->res()->create();
    $courseClass = CourseClass::factory()->forCourse($course)->create();
    $users = collect(range(1, 3))->map(fn (): User => createClassUser());

    $assignments = $users->map(fn (User $user): CourseClassUser => CourseClassUser::query()->create([
        'course_class_id' => $courseClass->getKey(),
        'user_id' => $user->getKey(),
        'assigned_at' => now(),
    ]));

    $this->deleteJson(route('admin.courses.classes.users.destroy-many', [$course, $courseClass]), [
        'assignment_ids' => $assignments->pluck('id')->all(),
    ])->assertOk();

    $assignments->each(function (CourseClassUser $assignment): void {
        expect($assignment->fresh()->trashed())->toBeTrue();
    });
});

it('rejects too many users and non standard users', function () {
    $course = Course::factory()->res()->create();
    $courseClass = CourseClass::factory()->forCourse($course)->create();
    $users = collect(range(1, 31))->map(fn (): User => createClassUser());
    $teacher = createClassTeacher();

    $this->postJson(route('admin.courses.classes.users.store', [$course, $courseClass]), [
        'user_ids' => $users->pluck('id')->all(),
    ])->assertUnprocessable();

    $this->postJson(route('admin.courses.classes.users.store', [$course, $courseClass]), [
        'user_ids' => [$teacher->getKey()],
    ])->assertUnprocessable();
});

it('searches class users with prefix matching and ignores too short terms', function () {
    $course = Course::factory()->res()->create();
    $matchingUser = createClassUser();
    $matchingUser->forceFill([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.test',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ])->save();

    $otherUser = createClassUser();
    $otherUser->forceFill([
        'name' => 'Luca',
        'surname' => 'Bianchi',
        'email' => 'luca.bianchi@example.test',
        'fiscal_code' => 'BNCLCU80A01H501Z',
    ])->save();

    CourseEnrollment::enroll($matchingUser, $course);

    $this->getJson(route('admin.courses.classes.search-users', ['course' => $course, 'search' => 'm']))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson(route('admin.courses.classes.search-users', ['course' => $course, 'search' => 'Ma']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $matchingUser->getKey())
        ->assertJsonMissing(['id' => $otherUser->getKey()]);

    $this->getJson(route('admin.courses.classes.search-users', ['course' => $course, 'search' => (string) $matchingUser->getKey()]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $matchingUser->getKey());
});

it('assigns teachers and syncs them to live and res modules only', function () {
    $course = Course::factory()->res()->create();
    $teacher = createClassTeacher();
    $liveModule = Module::factory()->create(['type' => 'live', 'belongsTo' => (string) $course->getKey()]);
    $videoModule = Module::factory()->create(['type' => 'video', 'belongsTo' => (string) $course->getKey()]);
    $courseClass = CourseClass::factory()->forModule($liveModule)->create();

    $this->postJson(route('admin.courses.classes.teachers.store', [$course, $courseClass]), [
        'teacher_ids' => [$teacher->getKey()],
    ])->assertCreated();

    expect(CourseClassTeacher::query()->where('course_class_id', $courseClass->getKey())->where('user_id', $teacher->getKey())->exists())->toBeTrue()
        ->and(ModuleTeacherEnrollment::query()->where('module_id', $liveModule->getKey())->where('user_id', $teacher->getKey())->exists())->toBeTrue()
        ->and(ModuleTeacherEnrollment::query()->where('module_id', $videoModule->getKey())->where('user_id', $teacher->getKey())->exists())->toBeFalse();
});

it('rejects assigning non teachers as class teachers', function () {
    $course = Course::factory()->res()->create();
    $courseClass = CourseClass::factory()->forCourse($course)->create();
    $user = createClassUser();

    $this->postJson(route('admin.courses.classes.teachers.store', [$course, $courseClass]), [
        'teacher_ids' => [$user->getKey()],
    ])->assertUnprocessable();
});

it('soft deletes classes and class assignments without deleting course enrollments or module assignments', function () {
    $course = Course::factory()->res()->create();
    $courseClass = CourseClass::factory()->forCourse($course)->create();
    $user = createClassUser();
    $teacher = createClassTeacher();
    $module = Module::factory()->create(['type' => 'live', 'belongsTo' => (string) $course->getKey()]);
    $userAssignment = CourseClassUser::query()->create([
        'course_class_id' => $courseClass->getKey(),
        'user_id' => $user->getKey(),
        'assigned_at' => now(),
    ]);
    $teacherAssignment = CourseClassTeacher::query()->create([
        'course_class_id' => $courseClass->getKey(),
        'user_id' => $teacher->getKey(),
        'assigned_at' => now(),
    ]);
    CourseEnrollment::enroll($user, $course);
    ModuleTeacherEnrollment::query()->create(['module_id' => $module->getKey(), 'user_id' => $teacher->getKey(), 'assigned_at' => now()]);

    $this->deleteJson(route('admin.courses.classes.users.destroy', [$course, $courseClass, $userAssignment]))->assertOk();
    $this->deleteJson(route('admin.courses.classes.teachers.destroy', [$course, $courseClass, $teacherAssignment]))->assertOk();

    expect($userAssignment->fresh()->trashed())->toBeTrue()
        ->and($teacherAssignment->fresh()->trashed())->toBeTrue()
        ->and(CourseEnrollment::query()->where('course_id', $course->getKey())->where('user_id', $user->getKey())->exists())->toBeTrue()
        ->and(ModuleTeacherEnrollment::query()->where('module_id', $module->getKey())->where('user_id', $teacher->getKey())->exists())->toBeTrue();

    $this->deleteJson(route('admin.courses.classes.destroy', [$course, $courseClass]))->assertOk();

    expect($courseClass->fresh()->trashed())->toBeTrue()
        ->and($course->fresh())->not->toBeNull();
});

function createClassUser(): User
{
    $user = User::query()->create([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'is_foreigner_or_immigrant' => false,
    ]);

    $user->assignRole('user');

    return $user;
}

function createClassTeacher(): User
{
    $user = User::query()->create([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'is_foreigner_or_immigrant' => false,
    ]);

    $user->assignRole('teacher');

    return $user;
}

function classPayload(Module $module, array $overrides = []): array
{
    return array_replace_recursive([
        'module_id' => $module->getKey(),
        'name' => 'Classe A',
        'schedules' => [[
            'starts_at_date' => '2026-06-01',
            'starts_at_time' => '09:00',
            'ends_at_date' => '2026-06-01',
            'ends_at_time' => '13:00',
        ]],
    ], $overrides);
}
