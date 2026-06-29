<?php

use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\RiskBasedRequirement;
use App\Models\User;
use App\Models\UserCertificate;
use App\Services\CourseRiskRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeTestUser(): User
{
    return User::forceCreate([
        'name' => 'Test',
        'surname' => 'User',
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'fiscal_code' => strtoupper(Str::random(16)),
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);
}

it('determines first achievement when the user has no certificate for the requirement', function () {
    $user = makeTestUser();
    $requirement = RiskBasedRequirement::factory()->create();

    $requiredType = app(CourseRiskRequirementService::class)
        ->determineRequiredValidityType($user, $requirement, now());

    expect($requiredType)->toBe(CourseRiskRequirementValidityType::FirstAchievement);
});

it('determines refresh when the user already has a valid certificate for the requirement', function () {
    $user = makeTestUser();
    $requirement = RiskBasedRequirement::factory()->limited(24)->create();

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'issued_at' => now()->subMonths(3)->toDateString(),
            'expires_at' => now()->addMonths(21)->toDateString(),
        ]);
    $certificate->riskBasedRequirements()->attach($requirement->getKey());

    $requiredType = app(CourseRiskRequirementService::class)
        ->determineRequiredValidityType($user, $requirement, now());

    expect($requiredType)->toBe(CourseRiskRequirementValidityType::Refresh);
});

it('switches between refresh and first achievement based on the formation reset window', function () {
    $user = makeTestUser();
    $requirement = RiskBasedRequirement::factory()
        ->limited(12)
        ->withFormationReset(5)
        ->create();

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'issued_at' => now()->subYears(5)->subMonths(6)->toDateString(),
            'expires_at' => now()->subMonths(6)->toDateString(),
        ]);
    $certificate->riskBasedRequirements()->attach($requirement->getKey());

    $service = app(CourseRiskRequirementService::class);

    expect($service->determineRequiredValidityType($user, $requirement, now()))
        ->toBe(CourseRiskRequirementValidityType::Refresh);

    $certificate->forceFill([
        'issued_at' => now()->subYears(8)->toDateString(),
        'expires_at' => now()->subYears(6)->toDateString(),
    ])->save();

    expect($service->determineRequiredValidityType($user, $requirement, now()))
        ->toBe(CourseRiskRequirementValidityType::FirstAchievement);
});

it('matches the course requirement validity type against the current user need', function () {
    $user = makeTestUser();
    $requirement = RiskBasedRequirement::factory()->create();
    $service = app(CourseRiskRequirementService::class);

    expect($service->courseRequirementMatchesUserNeed(
        $user,
        $requirement,
        CourseRiskRequirementValidityType::FirstAchievement,
        now(),
    ))->toBeTrue();

    expect($service->courseRequirementMatchesUserNeed(
        $user,
        $requirement,
        CourseRiskRequirementValidityType::Refresh,
        now(),
    ))->toBeFalse();

    expect($service->courseRequirementMatchesUserNeed(
        $user,
        $requirement,
        [
            CourseRiskRequirementValidityType::FirstAchievement,
            CourseRiskRequirementValidityType::Refresh,
        ],
        now(),
    ))->toBeTrue();
});

it('determines integrative when the user has a valid lower-risk certificate in the same progression group', function () {
    $user = makeTestUser();
    $lowRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::LOW)
        ->progressionGroup('specific-worker-training')
        ->limited(60)
        ->create();
    $mediumRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::MEDIUM)
        ->progressionGroup('specific-worker-training')
        ->limited(60)
        ->create();

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'issued_at' => now()->subMonths(2)->toDateString(),
            'expires_at' => now()->addMonths(58)->toDateString(),
        ]);
    $certificate->riskBasedRequirements()->attach($lowRequirement->getKey());

    $requiredType = app(CourseRiskRequirementService::class)
        ->determineRequiredCourseValidityType($user, $mediumRequirement, now());

    expect($requiredType)->toBe(CourseRiskRequirementValidityType::Integrative);
});

it('uses a higher-risk valid certificate to satisfy a lower-risk requirement until expiry', function () {
    $user = makeTestUser();
    $mediumRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::MEDIUM)
        ->progressionGroup('specific-worker-training')
        ->limited(60)
        ->create();
    $highRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->progressionGroup('specific-worker-training')
        ->limited(60)
        ->create();

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'issued_at' => now()->subMonths(4)->toDateString(),
            'expires_at' => now()->addMonths(56)->toDateString(),
        ]);
    $certificate->riskBasedRequirements()->attach($highRequirement->getKey());

    $coverage = app(CourseRiskRequirementService::class)
        ->bestValidCertificateCoverageForRequirement($user, $mediumRequirement, now());

    expect($coverage['certificate'])->not->toBeNull()
        ->and($coverage['requirement']?->is($highRequirement))->toBeTrue();
});

it('treats a valid unlimited certificate as coverage for a general multi-level requirement', function () {
    $user = makeTestUser();
    $generalRequirement = RiskBasedRequirement::factory()->unlimited()->create([
        'name' => 'Formazione generale',
        'risk_levels' => [RiskLevel::LOW, RiskLevel::MEDIUM, RiskLevel::HIGH],
        'risk_progression_group' => null,
    ]);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'issued_at' => now()->subMonths(2)->toDateString(),
            'expires_at' => null,
        ]);
    $certificate->riskBasedRequirements()->attach($generalRequirement->getKey());

    $coverage = app(CourseRiskRequirementService::class)
        ->bestValidCertificateCoverageForRequirement($user, $generalRequirement, now());

    expect($coverage['certificate'])
        ->not->toBeNull()
        ->and($coverage['certificate']?->is($certificate))->toBeTrue()
        ->and($coverage['requirement']?->is($generalRequirement))->toBeTrue();
});

it('requires a valid starting certificate to enroll in an integrative course', function () {
    $user = makeTestUser();
    $lowRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::LOW)
        ->progressionGroup('specific-worker-training')
        ->limited(60)
        ->create();
    $highRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->progressionGroup('specific-worker-training')
        ->limited(60)
        ->create();
    $course = Course::factory()->create();
    $course->riskBasedRequirements()->attach($highRequirement->getKey(), [
        'course_validity_types' => json_encode([CourseRiskRequirementValidityType::Integrative->value]),
        'integrative_start_risk_levels' => json_encode([RiskLevel::LOW->value]),
    ]);

    expect(app(CourseRiskRequirementService::class)->userCanEnrollInCourse($user, $course))->toBeFalse();

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'expires_at' => now()->addYear()->toDateString(),
        ]);
    $certificate->riskBasedRequirements()->attach($lowRequirement->getKey());

    expect(app(CourseRiskRequirementService::class)->userCanEnrollInCourse($user, $course))->toBeTrue();
});

it('does not require an integrative prerequisite when the same course also covers first achievement', function () {
    $user = makeTestUser();
    $highRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->progressionGroup('specific-worker-training')
        ->limited(60)
        ->create();
    $course = Course::factory()->create();
    $course->riskBasedRequirements()->attach($highRequirement->getKey(), [
        'course_validity_types' => json_encode([
            CourseRiskRequirementValidityType::FirstAchievement->value,
            CourseRiskRequirementValidityType::Integrative->value,
        ]),
        'integrative_start_risk_levels' => json_encode([RiskLevel::LOW->value]),
    ]);

    expect(app(CourseRiskRequirementService::class)->userCanEnrollInCourse($user, $course))->toBeTrue();
});

it('describes missing enrollment prerequisites with the missing requirement details', function () {
    $user = makeTestUser();
    $highRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->progressionGroup('specific-worker-training')
        ->limited(60)
        ->create(['name' => 'Formazione specifica rischio alto']);
    $course = Course::factory()->create();
    $course->riskBasedRequirements()->attach($highRequirement->getKey(), [
        'course_validity_types' => json_encode([CourseRiskRequirementValidityType::Integrative->value]),
        'integrative_start_risk_levels' => json_encode([RiskLevel::LOW->value]),
    ]);

    $message = app(CourseRiskRequirementService::class)->enrollmentEligibilityMessage($user, $course);

    expect($message)
        ->not->toBeNull()
        ->toContain('Requisiti mancanti:')
        ->toContain('Formazione specifica rischio alto')
        ->toContain('Primo conseguimento');
});

it('describes when an integrative enrollment is blocked by the allowed starting levels', function () {
    $user = makeTestUser();
    $mediumRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::MEDIUM)
        ->progressionGroup('specific-worker-training')
        ->limited(60)
        ->create();
    $highRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->progressionGroup('specific-worker-training')
        ->limited(60)
        ->create(['name' => 'Formazione specifica rischio alto']);
    $course = Course::factory()->create();
    $course->riskBasedRequirements()->attach($highRequirement->getKey(), [
        'course_validity_types' => json_encode([CourseRiskRequirementValidityType::Integrative->value]),
        'integrative_start_risk_levels' => json_encode([RiskLevel::LOW->value]),
    ]);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'issued_at' => now()->subMonth()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ]);
    $certificate->riskBasedRequirements()->attach($mediumRequirement->getKey());

    $message = app(CourseRiskRequirementService::class)->enrollmentEligibilityMessage($user, $course);

    expect($message)
        ->not->toBeNull()
        ->toContain('Formazione specifica rischio alto')
        ->toContain('Rischio Basso');
});

it('creates an internal risk certificate when an enrollment completes', function () {
    $user = makeTestUser();
    $course = Course::factory()->create([
        'title' => 'Corso spazi confinati',
        'status' => 'draft',
    ]);
    $module = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $requirement = RiskBasedRequirement::factory()->limited(24)->create([
        'name' => 'Spazi confinati',
    ]);
    $course->riskBasedRequirements()->attach($requirement->getKey(), [
        'course_validity_types' => json_encode([
            CourseRiskRequirementValidityType::FirstAchievement->value,
            CourseRiskRequirementValidityType::Refresh->value,
        ]),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);
    $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail()->markCompleted();

    $certificate = UserCertificate::query()->sole();

    expect($certificate->internal_course_id)->toBe($course->getKey())
        ->and($certificate->is_internal)->toBeTrue()
        ->and($certificate->name)->toBe('Corso spazi confinati')
        ->and($certificate->description)->toBe('Conseguito partecipando al corso interno "Corso spazi confinati" ('.$course->getKey().') come conseguimento iniziale')
        ->and($certificate->issued_at?->toDateString())->toBe($enrollment->fresh()->completed_at?->toDateString())
        ->and($certificate->expires_at?->toDateString())->toBe($enrollment->fresh()->completed_at?->copy()->addMonthsNoOverflow(24)->toDateString())
        ->and($certificate->riskBasedRequirements()->pluck('risk_based_requirements.id')->all())
        ->toEqualCanonicalizing([$requirement->getKey()]);
});

it('does not create a duplicate valid certificate for the same course and requirement', function () {
    $user = makeTestUser();
    $course = Course::factory()->create([
        'title' => 'Corso aggiornamento',
        'status' => 'draft',
    ]);
    $requirement = RiskBasedRequirement::factory()->limited(24)->create();
    $course->riskBasedRequirements()->attach($requirement->getKey(), [
        'course_validity_types' => json_encode([
            CourseRiskRequirementValidityType::FirstAchievement->value,
            CourseRiskRequirementValidityType::Refresh->value,
        ]),
    ]);

    $enrollment = CourseEnrollment::withoutEvents(fn (): CourseEnrollment => CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now()->subDay(),
        'completion_percentage' => 100,
    ]));

    $certificate = UserCertificate::factory()
        ->for($user)
        ->internal($course)
        ->create([
            'name' => 'Corso aggiornamento',
            'issued_at' => now()->subDay()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ]);
    $certificate->riskBasedRequirements()->attach($requirement->getKey());

    app(CourseRiskRequirementService::class)->syncCertificatesForEnrollment($enrollment->fresh());

    expect(UserCertificate::query()->count())->toBe(1);
});

it('backfills certificates for enrollments completed outside the observer flow', function () {
    $user = makeTestUser();
    $course = Course::factory()->create([
        'title' => 'Corso manuale',
        'status' => 'draft',
    ]);
    $requirement = RiskBasedRequirement::factory()->create([
        'name' => 'Manuale',
    ]);
    $course->riskBasedRequirements()->attach($requirement->getKey(), [
        'course_validity_types' => json_encode([
            CourseRiskRequirementValidityType::FirstAchievement->value,
            CourseRiskRequirementValidityType::Refresh->value,
        ]),
    ]);

    $enrollment = CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'status' => CourseEnrollment::STATUS_ASSIGNED,
        'completed_at' => null,
        'completion_percentage' => 0,
    ]);

    CourseEnrollment::withoutEvents(function () use ($enrollment): void {
        $enrollment->forceFill([
            'status' => CourseEnrollment::STATUS_COMPLETED,
            'completed_at' => now()->subDays(2),
            'completion_percentage' => 100,
        ])->save();
    });

    app(CourseRiskRequirementService::class)->syncCertificatesForCompletedEnrollments();

    $certificate = UserCertificate::query()->sole();

    expect($certificate->internal_course_id)->toBe($course->getKey())
        ->and($certificate->riskBasedRequirements()->pluck('risk_based_requirements.id')->all())
        ->toEqualCanonicalizing([$requirement->getKey()]);
});

it('does not create a certificate if integrative prerequisites are not met', function () {
    $user = makeTestUser();
    $progressionGroup = 'test_progression_group';

    $highRiskRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->limited(24)
        ->progressionGroup($progressionGroup)
        ->create(['name' => 'Rischio Alto']);

    $lowRiskRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::LOW)
        ->limited(24)
        ->progressionGroup($progressionGroup)
        ->create(['name' => 'Rischio Basso']);

    $integrativeCourse = Course::factory()->create([
        'title' => 'Corso Integrativo',
        'status' => 'draft',
    ]);

    $integrativeCourse->riskBasedRequirements()->attach($highRiskRequirement->getKey(), [
        'course_validity_types' => json_encode([CourseRiskRequirementValidityType::Integrative->value]),
        'integrative_start_risk_levels' => json_encode([RiskLevel::LOW->value]),
    ]);

    // User has no valid certificate for the required participation level
    // Even if enrolled (e.g., by admin), the certificate should not be generated
    $module = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $integrativeCourse->getKey(),
    ]);

    // Force enrollment bypass using withoutEvents
    $enrollment = CourseEnrollment::withoutEvents(fn (): CourseEnrollment => CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $integrativeCourse->getKey(),
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
        'course_validity_type' => CourseRiskRequirementValidityType::Integrative->value,
        'is_integrative_enrollment' => true,
    ]));
    $enrollment->moduleProgresses()->create([
        'module_id' => $module->getKey(),
        'status' => ModuleProgress::STATUS_COMPLETED,
    ]);
    $enrollment->forceFill([
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now(),
        'completion_percentage' => 100,
    ])->save();

    app(CourseRiskRequirementService::class)->syncCertificatesForEnrollment($enrollment->fresh());

    expect(UserCertificate::query()->count())->toBe(0)
        ->and($enrollment->fresh()->certificate_generation_error)->not()->toBeNull()
        ->and($enrollment->fresh()->certificate_generation_error)->toContain('integrative_prerequisites_not_met');
});

it('does not create a certificate if participation certificate is expired', function () {
    $user = makeTestUser();
    $progressionGroup = 'test_progression_group_expired';

    $highRiskRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->limited(24)
        ->progressionGroup($progressionGroup)
        ->create(['name' => 'Rischio Alto']);

    $lowRiskRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::LOW)
        ->limited(24)
        ->progressionGroup($progressionGroup)
        ->create(['name' => 'Rischio Basso']);

    $integrativeCourse = Course::factory()->create([
        'title' => 'Corso Integrativo',
        'status' => 'draft',
    ]);

    $integrativeCourse->riskBasedRequirements()->attach($highRiskRequirement->getKey(), [
        'course_validity_types' => json_encode([CourseRiskRequirementValidityType::Integrative->value]),
        'integrative_start_risk_levels' => json_encode([RiskLevel::LOW->value]),
    ]);

    // Create an expired certificate for the user
    $expiredCertificate = $user->userCertificates()->create([
        'name' => 'Certificato Scaduto',
        'is_internal' => false,
        'issued_at' => now()->subYears(2),
        'expires_at' => now()->subMonths(6),
    ]);
    $expiredCertificate->riskBasedRequirements()->attach($lowRiskRequirement->getKey());

    $module = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $integrativeCourse->getKey(),
    ]);

    // Force enrollment bypass using withoutEvents
    $enrollment = CourseEnrollment::withoutEvents(fn (): CourseEnrollment => CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $integrativeCourse->getKey(),
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
        'course_validity_type' => CourseRiskRequirementValidityType::Integrative->value,
        'is_integrative_enrollment' => true,
    ]));
    $enrollment->moduleProgresses()->create([
        'module_id' => $module->getKey(),
        'status' => ModuleProgress::STATUS_COMPLETED,
    ]);
    $enrollment->forceFill([
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now(),
        'completion_percentage' => 100,
    ])->save();

    app(CourseRiskRequirementService::class)->syncCertificatesForEnrollment($enrollment->fresh());

    expect(UserCertificate::where('is_internal', true)->count())->toBe(0)
        ->and($enrollment->fresh()->certificate_generation_error)->not()->toBeNull()
        ->and($enrollment->fresh()->certificate_generation_error)->toContain('participation_certificate_expired');
});
