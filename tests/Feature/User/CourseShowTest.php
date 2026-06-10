<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\User;
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
