<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\User;
use App\Models\Venue;
use App\Models\Video;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('shows review button for completed video modules only', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Viewer',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'fiscal_code' => strtoupper(Str::random(16)),
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('user');

    $course = Course::factory()->create([
        'status' => 'draft',
    ]);
    $video = Video::factory()->create();

    $completedVideoModule = Module::factory()->create([
        'title' => 'Video completato',
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
        'video_id' => $video->getKey(),
    ]);

    $completedQuizModule = Module::factory()->create([
        'title' => 'Quiz completato',
        'type' => Module::TYPE_LEARNING_QUIZ,
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);

    $enrollment->moduleProgresses()
        ->where('module_id', $completedVideoModule->getKey())
        ->firstOrFail()
        ->update(['status' => 'completed']);

    $enrollment->moduleProgresses()
        ->where('module_id', $completedQuizModule->getKey())
        ->firstOrFail()
        ->update(['status' => 'completed']);

    $response = $this->actingAs($user)
        ->get(route('user.courses.show', $course));

    $reviewUrl = route('user.courses.modules.player', [$course, $completedVideoModule]);
    $quizUrl = route('user.courses.modules.player', [$course, $completedQuizModule]);

    $response->assertOk()
        ->assertSee('Video completato')
        ->assertSee('Quiz completato')
        ->assertSee($reviewUrl, escape: false)
        ->assertDontSee($quizUrl, escape: false);
});

it('shows residential course start and venue address', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Viewer',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'fiscal_code' => strtoupper(Str::random(16)),
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('user');

    $venue = Venue::factory()->create(['address' => 'Via Roma 10']);
    $course = Course::factory()->res()->create([
        'venue_id' => $venue->getKey(),
        'status' => 'draft',
    ]);
    Module::factory()->create([
        'title' => 'Aula',
        'type' => Module::TYPE_RESIDENTIAL,
        'appointment_start_time' => '2026-07-01 09:30:00',
        'belongsTo' => (string) $course->getKey(),
    ]);

    CourseEnrollment::enroll($user, $course);

    $this->actingAs($user)
        ->get(route('user.courses.show', $course))
        ->assertOk()
        ->assertSeeText('Inizio')
        ->assertSeeText('01/07/2026 09:30')
        ->assertSeeText('Sede')
        ->assertSeeText('Via Roma 10');
});

it('links user courses index to the course detail page', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Viewer',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'fiscal_code' => strtoupper(Str::random(16)),
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('user');

    $course = Course::factory()->res()->create();
    CourseEnrollment::enroll($user, $course);

    $this->actingAs($user)
        ->get(route('user.courses.index'))
        ->assertOk()
        ->assertSee(route('user.courses.show', $course), escape: false);
});

it('shows qr modal for accessible residential modules instead of player link', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Viewer',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'fiscal_code' => strtoupper(Str::random(16)),
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('user');

    $course = Course::factory()->res()->create([
        'status' => 'draft',
    ]);
    $module = Module::factory()->create([
        'title' => 'Modulo RES',
        'type' => Module::TYPE_RESIDENTIAL,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);

    $expectedQrContent = base64_encode($user->getKey().'*'.$enrollment->getKey());
    $playerUrl = route('user.courses.modules.player', [$course, $module]);

    $this->actingAs($user)
        ->get(route('user.courses.show', $course))
        ->assertOk()
        ->assertSeeText('Codice QR presenza')
        ->assertSeeText('Mostra questo codice QR per registrare la tua presenza al corso.')
        ->assertSee('id="residential-attendance-qr-modal"', escape: false)
        ->assertSee('onclick="document.getElementById(\'residential-attendance-qr-modal\').showModal()"', escape: false)
        ->assertDontSee($playerUrl, escape: false)
        ->assertViewHas('residentialAttendanceQrCodeContent', $expectedQrContent)
        ->assertViewHas('residentialAttendanceQrCodeDataUri', fn (?string $value): bool => is_string($value) && str_starts_with($value, 'data:image/svg+xml;base64,'));
});
