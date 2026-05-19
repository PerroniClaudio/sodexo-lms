<?php

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassUser;
use App\Models\CourseEnrollment;
use App\Models\LiveStreamSession;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('shows waiting before the class scheduled start time', function () {
    Carbon::setTestNow('2026-06-01 08:00:00');
    $user = actingAsRole('user');
    [$course, $module] = liveCourseAndModule('2026-06-01 07:00:00', '2026-06-01 18:00:00');
    assignUserToClass($course, $user, '2026-06-01 10:00:00', '2026-06-01 12:00:00');
    CourseEnrollment::enroll($user, $course);
    LiveStreamSession::factory()->create(['module_id' => $module->getKey(), 'teacher_user_id' => $user->getKey(), 'status' => LiveStreamSession::STATUS_LIVE]);

    $this->actingAs($user)
        ->get(route('user.live-stream.player', $module))
        ->assertOk()
        ->assertSeeText('Diretta non ancora disponibile')
        ->assertSeeText('01/06/2026 10:00');
});

it('shows ended after the class scheduled end time', function () {
    Carbon::setTestNow('2026-06-01 13:00:00');
    $user = actingAsRole('user');
    [$course, $module] = liveCourseAndModule('2026-06-01 07:00:00', '2026-06-01 18:00:00');
    assignUserToClass($course, $user, '2026-06-01 10:00:00', '2026-06-01 12:00:00');
    CourseEnrollment::enroll($user, $course);
    LiveStreamSession::factory()->create(['module_id' => $module->getKey(), 'teacher_user_id' => $user->getKey(), 'status' => LiveStreamSession::STATUS_LIVE]);

    $this->actingAs($user)
        ->get(route('user.live-stream.player', $module))
        ->assertOk()
        ->assertSeeText('Diretta terminata');
});

it('allows access during the class scheduled window', function () {
    Carbon::setTestNow('2026-06-01 10:30:00');
    $user = actingAsRole('user');
    [$course, $module] = liveCourseAndModule('2026-06-01 07:00:00', '2026-06-01 18:00:00');
    assignUserToClass($course, $user, '2026-06-01 10:00:00', '2026-06-01 12:00:00');
    CourseEnrollment::enroll($user, $course);
    LiveStreamSession::factory()->create(['module_id' => $module->getKey(), 'teacher_user_id' => $user->getKey(), 'status' => LiveStreamSession::STATUS_LIVE]);

    $this->actingAs($user)
        ->get(route('user.live-stream.player', $module))
        ->assertOk()
        ->assertDontSeeText('Diretta non ancora disponibile')
        ->assertSeeText('Materiale didattico');
});

it('uses module appointment fallback for students without a class', function () {
    Carbon::setTestNow('2026-06-01 08:30:00');
    $user = actingAsRole('user');
    [$course, $module] = liveCourseAndModule('2026-06-01 08:00:00', '2026-06-01 09:00:00');
    CourseEnrollment::enroll($user, $course);
    LiveStreamSession::factory()->create(['module_id' => $module->getKey(), 'teacher_user_id' => $user->getKey(), 'status' => LiveStreamSession::STATUS_LIVE]);

    $this->actingAs($user)
        ->get(route('user.live-stream.player', $module))
        ->assertOk()
        ->assertSeeText('Materiale didattico');
});

it('resolves different windows for two students on the same live module', function () {
    Carbon::setTestNow('2026-06-01 10:30:00');
    $firstUser = actingAsRole('user');
    $secondUser = createScheduleUser();
    [$course, $module] = liveCourseAndModule('2026-06-01 07:00:00', '2026-06-01 18:00:00');
    assignUserToClass($course, $firstUser, '2026-06-01 10:00:00', '2026-06-01 12:00:00');
    assignUserToClass($course, $secondUser, '2026-06-01 14:00:00', '2026-06-01 16:00:00');
    CourseEnrollment::enroll($firstUser, $course);
    CourseEnrollment::enroll($secondUser, $course);
    LiveStreamSession::factory()->create(['module_id' => $module->getKey(), 'teacher_user_id' => $firstUser->getKey(), 'status' => LiveStreamSession::STATUS_LIVE]);

    $this->actingAs($firstUser)
        ->get(route('user.live-stream.player', $module))
        ->assertOk()
        ->assertSeeText('Materiale didattico');

    $this->actingAs($secondUser)
        ->get(route('user.live-stream.player', $module))
        ->assertOk()
        ->assertSeeText('01/06/2026 14:00');
});

function liveCourseAndModule(string $startsAt, string $endsAt): array
{
    $course = Course::factory()->res()->create();
    $module = Module::factory()->create([
        'type' => 'live',
        'status' => 'published',
        'is_live_teacher' => true,
        'appointment_start_time' => Carbon::parse($startsAt),
        'appointment_end_time' => Carbon::parse($endsAt),
        'belongsTo' => (string) $course->getKey(),
    ]);

    return [$course, $module];
}

function assignUserToClass(Course $course, User $user, string $startsAt, string $endsAt): CourseClass
{
    $courseClass = CourseClass::factory()->forCourse($course)->create([
        'starts_at' => Carbon::parse($startsAt),
        'ends_at' => Carbon::parse($endsAt),
    ]);

    CourseClassUser::query()->create([
        'course_class_id' => $courseClass->getKey(),
        'user_id' => $user->getKey(),
        'assigned_at' => now(),
    ]);

    return $courseClass;
}

function createScheduleUser(): User
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
