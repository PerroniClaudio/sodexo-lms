<?php

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassTeacher;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleTeacherEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

test('teacher user activity endpoint returns latest module and course completions', function () {
    $teacher = actingAsRole('teacher');

    $asyncCourse = Course::factory()->async()->create([
        'title' => 'React Avanzato',
        'status' => 'draft',
    ]);
    $residentialCourse = Course::factory()->res()->create([
        'title' => 'Sicurezza Negozio',
        'status' => 'draft',
    ]);
    $ignoredCourse = Course::factory()->create([
        'title' => 'Corso Ignorato',
        'status' => 'draft',
    ]);

    $asyncModule = Module::factory()->create([
        'belongsTo' => (string) $asyncCourse->getKey(),
        'title' => 'Modulo React 1',
        'type' => Module::TYPE_SCORM,
        'status' => 'published',
    ]);
    $residentialModule = Module::factory()->create([
        'belongsTo' => (string) $residentialCourse->getKey(),
        'title' => 'Modulo Sicurezza 1',
        'type' => Module::TYPE_RESIDENTIAL,
        'status' => 'published',
    ]);
    $ignoredModule = Module::factory()->create([
        'belongsTo' => (string) $ignoredCourse->getKey(),
        'title' => 'Modulo Ignorato',
        'type' => Module::TYPE_SCORM,
    ]);

    $asyncCourse->update(['status' => 'published']);
    $residentialCourse->update(['status' => 'published']);

    ModuleTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'module_id' => $asyncModule->getKey(),
    ]);

    $courseClass = CourseClass::factory()->forModule($residentialModule)->create([
        'name' => 'Classe Milano',
    ]);

    CourseClassTeacher::factory()->create([
        'course_class_id' => $courseClass->getKey(),
        'user_id' => $teacher->getKey(),
    ]);

    $firstLearner = User::query()->create([
        'email' => 'claudio@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => 'Claudio',
        'surname' => 'Perroni',
        'fiscal_code' => 'CLDPNN90A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $secondLearner = User::query()->create([
        'email' => 'sofia@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => 'Sofia',
        'surname' => 'Rossi',
        'fiscal_code' => 'SFRRSS90A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $thirdLearner = User::query()->create([
        'email' => 'luca@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => 'Luca',
        'surname' => 'Bianchi',
        'fiscal_code' => 'LCABNC90A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);

    $firstAsyncEnrollment = CourseEnrollment::factory()->create([
        'user_id' => $firstLearner->getKey(),
        'course_id' => $asyncCourse->getKey(),
        'current_module_id' => $asyncModule->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now()->subMinutes(5),
    ]);
    $secondAsyncEnrollment = CourseEnrollment::factory()->create([
        'user_id' => $secondLearner->getKey(),
        'course_id' => $asyncCourse->getKey(),
        'current_module_id' => $asyncModule->getKey(),
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
    ]);
    $residentialEnrollment = CourseEnrollment::factory()->create([
        'user_id' => $thirdLearner->getKey(),
        'course_id' => $residentialCourse->getKey(),
        'current_module_id' => $residentialModule->getKey(),
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
    ]);
    $ignoredEnrollment = CourseEnrollment::factory()->create([
        'user_id' => $secondLearner->getKey(),
        'course_id' => $ignoredCourse->getKey(),
        'current_module_id' => $ignoredModule->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now()->subMinutes(1),
    ]);

    ModuleProgress::factory()->create([
        'course_user_id' => $firstAsyncEnrollment->getKey(),
        'module_id' => $asyncModule->getKey(),
        'status' => ModuleProgress::STATUS_COMPLETED,
        'completed_at' => now()->subMinutes(10),
    ]);
    ModuleProgress::factory()->create([
        'course_user_id' => $secondAsyncEnrollment->getKey(),
        'module_id' => $asyncModule->getKey(),
        'status' => ModuleProgress::STATUS_COMPLETED,
        'completed_at' => now()->subMinutes(20),
    ]);
    ModuleProgress::factory()->create([
        'course_user_id' => $residentialEnrollment->getKey(),
        'module_id' => $residentialModule->getKey(),
        'status' => ModuleProgress::STATUS_COMPLETED,
        'completed_at' => now()->subMinutes(15),
    ]);
    ModuleProgress::factory()->create([
        'course_user_id' => $ignoredEnrollment->getKey(),
        'module_id' => $ignoredModule->getKey(),
        'status' => ModuleProgress::STATUS_COMPLETED,
        'completed_at' => now()->subMinutes(2),
    ]);

    $response = $this->getJson(route('teacher.dashboard.user-activity'));

    $response->assertOk()
        ->assertJsonCount(4, 'activities')
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('activities.0', fn (AssertableJson $activity) => $activity
                ->where('type', 'course_completed')
                ->where('label', 'Corso completato')
                ->where('message', 'Claudio P. ha completato il corso')
                ->where('context', 'React Avanzato')
                ->whereType('occurred_at', 'string')
                ->whereType('occurred_at_label', 'string')
                ->etc()
            )
            ->has('activities.1', fn (AssertableJson $activity) => $activity
                ->where('type', 'module_completed')
                ->where('label', 'Modulo completato')
                ->where('message', 'Claudio P. ha completato Modulo React 1')
                ->where('context', 'React Avanzato')
                ->etc()
            )
            ->has('activities.2', fn (AssertableJson $activity) => $activity
                ->where('type', 'module_completed')
                ->where('message', 'Luca B. ha completato Modulo Sicurezza 1')
                ->where('context', 'Sicurezza Negozio')
                ->etc()
            )
            ->has('activities.3', fn (AssertableJson $activity) => $activity
                ->where('type', 'module_completed')
                ->where('message', 'Sofia R. ha completato Modulo React 1')
                ->where('context', 'React Avanzato')
                ->etc()
            )
            ->etc()
        );
});

test('teacher user activity endpoint requires authentication', function () {
    $this->getJson(route('teacher.dashboard.user-activity'))
        ->assertUnauthorized();
});

test('teacher fake user activity endpoint returns five mocked activities', function () {
    actingAsRole('teacher');

    $response = $this->getJson(route('teacher.dashboard.user-activity.fake'));

    $response->assertOk()
        ->assertJsonCount(5, 'activities')
        ->assertJsonPath('activities.0.type', 'module_completed')
        ->assertJsonPath('activities.0.label', 'Modulo completato')
        ->assertJsonPath('activities.1.type', 'course_completed')
        ->assertJsonPath('activities.4.context', 'Onboarding Store Manager');
});
