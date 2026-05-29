<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user dashboard courses stats endpoint returns aggregated data', function () {
    $user = actingAsRole('user');

    $firstCourse = Course::factory()->create([
        'title' => 'Corso HACCP',
    ]);

    $secondCourse = Course::factory()->create([
        'title' => 'Corso Sicurezza',
    ]);

    $firstModule = Module::factory()->create([
        'belongsTo' => (string) $firstCourse->getKey(),
        'order' => 1,
        'type' => 'video',
    ]);

    $secondModule = Module::factory()->create([
        'belongsTo' => (string) $secondCourse->getKey(),
        'order' => 1,
        'type' => 'video',
    ]);

    $firstEnrollment = CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $firstCourse->getKey(),
        'completion_percentage' => 75,
        'last_accessed_at' => now(),
    ]);

    $secondEnrollment = CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $secondCourse->getKey(),
        'completion_percentage' => 25,
        'last_accessed_at' => now()->subDay(),
    ]);

    ModuleProgress::factory()->create([
        'course_user_id' => $firstEnrollment->getKey(),
        'module_id' => $firstModule->getKey(),
        'last_accessed_at' => now(),
        'time_spent_seconds' => 5400,
    ]);

    ModuleProgress::factory()->create([
        'course_user_id' => $secondEnrollment->getKey(),
        'module_id' => $secondModule->getKey(),
        'last_accessed_at' => now()->subDay(),
        'time_spent_seconds' => 1800,
    ]);

    $response = $this->getJson(route('user.dashboard.courses-stats'));

    $response->assertOk()
        ->assertJsonPath('overall_progress', 50)
        ->assertJsonPath('remaining_progress', 50)
        ->assertJsonPath('courses.0.title', 'Corso HACCP')
        ->assertJsonPath('courses.0.progress', 75)
        ->assertJsonPath('courses.1.title', 'Corso Sicurezza')
        ->assertJsonPath('courses.1.progress', 25)
        ->assertJsonCount(7, 'weekly_activity.labels')
        ->assertJsonCount(7, 'weekly_activity.hours');

    expect(collect($response->json('weekly_activity.hours'))->sum())->toBe(2.0);
});

test('user dashboard courses stats endpoint requires authentication', function () {
    $this->getJson(route('user.dashboard.courses-stats'))
        ->assertUnauthorized();
});
