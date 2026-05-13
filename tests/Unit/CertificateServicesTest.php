<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CustomCertificate;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\Certificates\CertificateVariableResolver;
use App\Services\Certificates\CustomCertificateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('resolves a course-specific template before the generic fallback', function () {
    $course = Course::factory()->create();

    $generic = CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'course_ids' => null,
    ]);

    $specific = CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'course_ids' => [$course->getKey()],
    ]);

    $resolved = app(CustomCertificateResolver::class)->resolve(CustomCertificate::TYPE_PARTICIPATION, $course);

    expect($resolved?->getKey())->toBe($specific->getKey())
        ->and($resolved?->getKey())->not->toBe($generic->getKey());
});

it('uses the generic template fallback when no course-specific template exists', function () {
    $course = Course::factory()->create();

    $generic = CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_COMPLETION,
        'course_ids' => null,
    ]);

    $resolved = app(CustomCertificateResolver::class)->resolve(CustomCertificate::TYPE_COMPLETION, $course);

    expect($resolved?->getKey())->toBe($generic->getKey());
});

it('marks participation eligible when course is completed and satisfaction quiz is completed', function () {
    [$enrollment, $learningModule, $satisfactionModule] = completedEnrollmentWithQuizProgress();

    $enrollment->moduleProgresses()->create([
        'module_id' => $learningModule->getKey(),
        'status' => ModuleProgress::STATUS_FAILED,
        'quiz_attempts' => 1,
        'quiz_score' => 1,
        'quiz_total_score' => 10,
    ]);

    $eligible = app(CertificateEligibilityService::class)
        ->isEligible($enrollment->fresh(['course.modules', 'moduleProgresses']), CustomCertificate::TYPE_PARTICIPATION);

    expect($eligible)->toBeTrue();
});

it('marks completion eligible only when all learning quizzes are passed and satisfaction quiz is completed', function () {
    [$enrollment, $learningModule, $satisfactionModule] = completedEnrollmentWithQuizProgress();

    $enrollment->moduleProgresses()->create([
        'module_id' => $learningModule->getKey(),
        'status' => ModuleProgress::STATUS_COMPLETED,
        'quiz_attempts' => 1,
        'quiz_score' => 10,
        'quiz_total_score' => 10,
        'passed_at' => now(),
        'completed_at' => now(),
    ]);

    $eligible = app(CertificateEligibilityService::class)
        ->isEligible($enrollment->fresh(['course.modules', 'moduleProgresses']), CustomCertificate::TYPE_COMPLETION);

    expect($eligible)->toBeTrue();
});

it('resolves variables including appointment data and fallback dates', function () {
    $course = Course::factory()->create([
        'title' => 'Corso ECM',
    ]);

    Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'type' => 'res',
        'order' => 1,
        'appointment_start_time' => now()->setTime(9, 0),
        'appointment_end_time' => now()->setTime(13, 30),
    ]);

    $user = testCertificateUser([
        'name' => 'Laura',
        'surname' => 'Bianchi',
        'fiscal_code' => 'BNCLRA80A01H501Z',
    ]);

    $variables = app(CertificateVariableResolver::class)->resolve($course->fresh('modules'), $user);

    expect($variables['${TITOLO}'])->toBe('Corso ECM')
        ->and($variables['${NOME_UTENTE}'])->toBe('Laura')
        ->and($variables['${COGNOME_UTENTE}'])->toBe('Bianchi')
        ->and($variables['${CODICE_FISCALE_UTENTE}'])->toBe('BNCLRA80A01H501Z')
        ->and($variables['${DATA_COMPLETAMENTO_CORSO}'])->toBe(today()->format('d/m/Y'))
        ->and($variables['${DATA_CORSO}'])->toBe(today()->format('d/m/Y'))
        ->and($variables['${ORARIO_CORSO}'])->toBe('09:00 - 13:30')
        ->and($variables['${ORE}'])->toBe('4,50');
});

function completedEnrollmentWithQuizProgress(): array
{
    $course = Course::factory()->create();
    $user = testCertificateUser();

    $learningModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'type' => 'learning_quiz',
        'order' => 1,
    ]);

    $satisfactionModule = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'type' => 'satisfaction_quiz',
        'order' => 2,
    ]);

    $enrollment = CourseEnrollment::factory()->create([
        'course_id' => $course->getKey(),
        'user_id' => $user->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    $enrollment->moduleProgresses()->create([
        'module_id' => $satisfactionModule->getKey(),
        'status' => ModuleProgress::STATUS_COMPLETED,
        'completed_at' => now(),
        'passed_at' => now(),
    ]);

    return [$enrollment, $learningModule, $satisfactionModule];
}

function testCertificateUser(array $attributes = []): User
{
    return User::query()->create(array_merge([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'is_foreigner_or_immigrant' => false,
    ], $attributes));
}
