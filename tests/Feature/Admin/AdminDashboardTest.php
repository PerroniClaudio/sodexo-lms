<?php

use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleQuizSubmission;
use App\Models\RiskBasedRequirement;
use App\Models\SatisfactionSurveyAnswer;
use App\Models\SatisfactionSurveyQuestion;
use App\Models\SatisfactionSurveySubmission;
use App\Models\SatisfactionSurveySubmissionAnswer;
use App\Models\SatisfactionSurveyTemplate;
use App\Models\User;
use App\Models\UserCertificate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function dashboardUser(string $role, array $attributes = []): User
{
    $user = User::query()->create(array_merge([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => UserStatus::ACTIVE,
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'is_foreigner_or_immigrant' => false,
    ], $attributes));

    $user->assignRole($role);

    return $user;
}

it('renders the admin dashboard with core cards and data', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = dashboardUser('admin');
    $this->actingAs($admin);

    $course = Course::factory()->create([
        'title' => 'Corso Sicurezza',
        'status' => 'draft',
        'type' => 'res',
    ]);

    $resModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'type' => Module::TYPE_RESIDENTIAL,
        'title' => 'Modulo RES',
        'status' => 'published',
    ]);

    $courseClass = CourseClass::factory()->forModule($resModule)->create([
        'name' => 'Aula Milano',
    ]);
    $courseClass->schedules()->delete();
    $courseClass->schedules()->create([
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDays(3)->addHours(4),
    ]);
    $course->update(['status' => 'published']);

    $asyncCourse = Course::factory()->async()->create([
        'title' => 'Corso Async',
        'status' => 'draft',
    ]);

    Module::factory()->create([
        'belongsTo' => (string) $asyncCourse->getKey(),
        'type' => Module::TYPE_SCORM,
        'title' => 'Modulo Async',
        'appointment_start_time' => now()->addDays(2),
        'appointment_end_time' => now()->addDays(2)->addHour(),
        'status' => 'published',
    ]);
    $asyncCourse->update(['status' => 'published']);

    $learner = dashboardUser('user', [
        'account_state' => UserStatus::ACTIVE,
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
    ]);

    CourseEnrollment::factory()->create([
        'user_id' => $learner->getKey(),
        'course_id' => $course->getKey(),
        'current_module_id' => $resModule->getKey(),
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
        'started_at' => now()->subDays(10),
        'last_accessed_at' => now()->subDays(8),
        'completion_percentage' => 40,
    ]);

    ModuleQuizSubmission::query()->create([
        'module_id' => $resModule->getKey(),
        'user_id' => $learner->getKey(),
        'status' => ModuleQuizSubmission::STATUS_NEEDS_REVIEW,
        'source_type' => ModuleQuizSubmission::SOURCE_UPLOAD,
    ]);

    $requirement = RiskBasedRequirement::factory()->create(['name' => 'Formazione generale']);
    $certificate = UserCertificate::factory()->create([
        'user_id' => $learner->getKey(),
        'internal_course_id' => $course->getKey(),
        'issued_at' => now()->subDays(5),
        'expires_at' => now()->addDays(10),
    ]);
    $certificate->riskBasedRequirements()->attach($requirement->getKey());

    $template = SatisfactionSurveyTemplate::query()->create([
        'is_active' => true,
        'created_by' => $admin->getKey(),
        'activated_at' => now(),
    ]);
    $question = SatisfactionSurveyQuestion::query()->create([
        'satisfaction_survey_template_id' => $template->getKey(),
        'sort_order' => 1,
        'text' => 'Come valuti il corso?',
        'input_type' => 'radio',
    ]);
    $answerLow = SatisfactionSurveyAnswer::query()->create([
        'satisfaction_survey_question_id' => $question->getKey(),
        'sort_order' => 1,
        'text' => '1',
    ]);
    $answerHigh = SatisfactionSurveyAnswer::query()->create([
        'satisfaction_survey_question_id' => $question->getKey(),
        'sort_order' => 5,
        'text' => '5',
    ]);
    collect([2, 3, 4])->each(function (int $index) use ($question): void {
        SatisfactionSurveyAnswer::query()->create([
            'satisfaction_survey_question_id' => $question->getKey(),
            'sort_order' => $index,
            'text' => (string) $index,
        ]);
    });
    $firstSubmission = SatisfactionSurveySubmission::query()->create([
        'satisfaction_survey_template_id' => $template->getKey(),
        'course_id' => $course->getKey(),
        'module_id' => $resModule->getKey(),
        'submitted_at' => now(),
    ]);
    SatisfactionSurveySubmissionAnswer::query()->create([
        'satisfaction_survey_submission_id' => $firstSubmission->getKey(),
        'satisfaction_survey_question_id' => $question->getKey(),
        'satisfaction_survey_answer_id' => $answerHigh->getKey(),
    ]);

    $secondSubmission = SatisfactionSurveySubmission::query()->create([
        'satisfaction_survey_template_id' => $template->getKey(),
        'course_id' => $course->getKey(),
        'module_id' => $resModule->getKey(),
        'submitted_at' => now(),
    ]);
    SatisfactionSurveySubmissionAnswer::query()->create([
        'satisfaction_survey_submission_id' => $secondSubmission->getKey(),
        'satisfaction_survey_question_id' => $question->getKey(),
        'satisfaction_survey_answer_id' => $answerHigh->getKey(),
    ]);

    $thirdSubmission = SatisfactionSurveySubmission::query()->create([
        'satisfaction_survey_template_id' => $template->getKey(),
        'course_id' => $course->getKey(),
        'module_id' => $resModule->getKey(),
        'submitted_at' => now(),
    ]);
    SatisfactionSurveySubmissionAnswer::query()->create([
        'satisfaction_survey_submission_id' => $thirdSubmission->getKey(),
        'satisfaction_survey_question_id' => $question->getKey(),
        'satisfaction_survey_answer_id' => $answerLow->getKey(),
    ]);

    $response = $this->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSeeText('Andamento formazione');
    $response->assertSeeText('Utenti da sollecitare');
    $response->assertSeeText('Compliance / rischio');
    $response->assertSeeText('Calendario attività');
    $response->assertSeeText('Valutazione');
    $response->assertSeeText('RES senza documenti');
    $response->assertSeeText('Gradimento');
    $response->assertSeeText('Attestati');
    $response->assertSeeText('Mario Rossi');
    $response->assertSeeText('Come valuti il corso?');
    $response->assertSee(route('admin.dashboard.follow-up-users.export'), escape: false);
});

it('exports follow up users as csv', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = dashboardUser('admin');
    $learner = dashboardUser('user', [
        'name' => 'Luigi',
        'surname' => 'Verdi',
        'email' => 'luigi@example.com',
    ]);
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
    ]);

    CourseEnrollment::factory()->create([
        'user_id' => $learner->getKey(),
        'course_id' => $course->getKey(),
        'current_module_id' => $module->getKey(),
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
        'started_at' => Carbon::now()->subDays(20),
        'last_accessed_at' => Carbon::now()->subDays(16),
        'completion_percentage' => 55,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.dashboard.follow-up-users.export', ['inactive_days' => 15]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Luigi Verdi');
});

it('returns admin dashboard calendar events for residential and async modules', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = dashboardUser('admin');
    $this->actingAs($admin);

    $resCourse = Course::factory()->res()->create([
        'title' => 'Corso RES Aggregato',
        'status' => 'draft',
    ]);
    $resModule = Module::factory()->create([
        'belongsTo' => (string) $resCourse->getKey(),
        'type' => Module::TYPE_RESIDENTIAL,
        'title' => 'Modulo RES Aggregato',
        'status' => 'published',
    ]);
    $resClass = CourseClass::factory()->forModule($resModule)->create([
        'name' => 'Classe RES',
    ]);
    $resCourse->update(['status' => 'published']);

    $asyncCourse = Course::factory()->async()->create([
        'title' => 'Corso Async Aggregato',
        'status' => 'draft',
    ]);
    $asyncModule = Module::factory()->create([
        'belongsTo' => (string) $asyncCourse->getKey(),
        'type' => Module::TYPE_SCORM,
        'title' => 'Modulo Async Aggregato',
        'appointment_start_time' => now()->addDay(),
        'appointment_end_time' => now()->addDay()->addHour(),
        'status' => 'published',
    ]);
    $asyncCourse->update(['status' => 'published']);

    $this->getJson(route('admin.dashboard.calendar-events'))
        ->assertOk()
        ->assertJsonFragment([
            'title' => 'Modulo RES Aggregato',
            'course_title' => 'Corso RES Aggregato',
            'type' => 'res',
        ])
        ->assertJsonFragment([
            'title' => 'Modulo Async Aggregato',
            'course_title' => 'Corso Async Aggregato',
            'type' => 'async',
        ]);
});
