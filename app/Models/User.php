<?php

namespace App\Models;

use App\Enums\OnboardingStep;
use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Services\CourseRiskRequirementService;
use App\Services\RiskCalculationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'email',
        'password',
        'account_state',
        'profile_completed_at',
        'last_data_update_request',
        'onboarding_step',
        'name',
        'surname',
        'fiscal_code',
        'birth_date',
        'employment_start_date',
        'employment_end_date',
        'birth_place',
        'citizenship_country_id',
        'gender',
        'phone_prefix',
        'phone',
        'home_country_id',
        'home_region_id',
        'home_province_id',
        'home_city_id',
        'address',
        'postal_code',
        'job_unit_id',
        'job_category_id',
        'job_level_id',
        'job_task_id',
        'job_role_id',
        'job_sector_id',
        'company_division_id',
        'is_foreigner_or_immigrant',
        'declared_language_level_id',
        'verified_language_level_id',
        'needs_language_level_verification',
        'notes',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'datapsw' => 'datetime',
            'data_richiesta_mail' => 'datetime',
            'profile_completed_at' => 'datetime',
            'last_data_update_request' => 'datetime',
            'birth_date' => 'date',
            'employment_start_date' => 'date',
            'employment_end_date' => 'date',
            'citizenship_country_id' => 'integer',
            'is_foreigner_or_immigrant' => 'boolean',
            'declared_language_level_id' => 'integer',
            'verified_language_level_id' => 'integer',
            'company_division_id' => 'integer',
            'needs_language_level_verification' => 'boolean',
            'account_state' => UserStatus::class,
            'onboarding_step' => OnboardingStep::class,
            'requirements_last_calculated_at' => 'datetime',
        ];
    }

    public function declaredLanguageLevel(): BelongsTo
    {
        return $this->belongsTo(LanguageLevel::class, 'declared_language_level_id');
    }

    public function verifiedLanguageLevel(): BelongsTo
    {
        return $this->belongsTo(LanguageLevel::class, 'verified_language_level_id');
    }

    /**
     * Job Unit relazione
     */
    public function jobUnit(): BelongsTo
    {
        return $this->belongsTo(JobUnit::class);
    }

    /**
     * Job Category relazione
     */
    public function jobCategory(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class);
    }

    /**
     * Job Level relazione
     */
    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class);
    }

    /**
     * Job Task relazione (mansione)
     */
    public function jobTask(): BelongsTo
    {
        return $this->belongsTo(JobTask::class, 'job_task_id');
    }

    public function jobTasks(): BelongsToMany
    {
        return $this->belongsToMany(JobTask::class, 'job_task_user')
            ->withPivot(['id', 'starts_at', 'ends_at'])
            ->withTimestamps();
    }

    /**
     * Job Role relazione
     */
    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }

    /**
     * Job Sector relazione
     */
    public function jobSector(): BelongsTo
    {
        return $this->belongsTo(JobSector::class);
    }

    public function companyDivision(): BelongsTo
    {
        return $this->belongsTo(CompanyDivision::class);
    }

    public function administeredCompanyDivisions(): BelongsToMany
    {
        return $this->belongsToMany(CompanyDivision::class, 'company_division_admin')
            ->withTimestamps();
    }

    public function activeCompanyDivision(): ?CompanyDivision
    {
        $divisionId = session('active_company_division_id');

        return $divisionId === null
            ? null
            : $this->administeredCompanyDivisions()->whereKey($divisionId)->first();
    }

    /**
     * Get the course enrollments for the user.
     */
    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function userCertificates(): HasMany
    {
        return $this->hasMany(UserCertificate::class);
    }

    public function jobBasedRequirements(): BelongsToMany
    {
        return $this->belongsToMany(JobBasedRequirement::class, 'job_based_requirement_user')
            ->withPivot(['is_active', 'valid_from', 'calculated_at'])
            ->withTimestamps();
    }

    public function courseFacultyMembers(): HasMany
    {
        return $this->hasMany(CourseFacultyMember::class);
    }

    public function moduleTeacherEnrollments(): HasMany
    {
        return $this->hasMany(ModuleTeacherEnrollment::class);
    }

    public function moduleTutorEnrollments(): HasMany
    {
        return $this->hasMany(ModuleTutorEnrollment::class);
    }

    public function courseClassAssignments(): HasMany
    {
        return $this->hasMany(CourseClassUser::class);
    }

    public function teachingCourseClassAssignments(): HasMany
    {
        return $this->hasMany(CourseClassTeacher::class);
    }

    /**
     * Get the courses assigned to the user.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_user')
            ->withPivot([
                'id',
                'current_module_id',
                'status',
                'assigned_at',
                'started_at',
                'completed_at',
                'expires_at',
                'last_accessed_at',
                'completion_percentage',
                'deleted_at',
            ])
            ->withTimestamps();
    }

    public function teachingModules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_teacher_enrollments')
            ->withPivot([
                'id',
                'assigned_at',
                'deleted_at',
            ])
            ->withTimestamps();
    }

    public function tutoringModules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_tutor_enrollments')
            ->withPivot([
                'id',
                'assigned_at',
                'deleted_at',
            ])
            ->withTimestamps();
    }

    public function getTeachingCoursesQuery(): Builder
    {
        return Course::query()
            ->select('courses.*')
            ->selectRaw('COUNT(DISTINCT module_teacher_enrollments.module_id) as assigned_modules_count')
            ->join('modules', 'modules.belongsTo', '=', 'courses.id')
            ->join('module_teacher_enrollments', 'module_teacher_enrollments.module_id', '=', 'modules.id')
            ->where('module_teacher_enrollments.user_id', $this->getKey())
            ->whereNull('courses.deleted_at')
            ->whereNull('modules.deleted_at')
            ->whereNull('module_teacher_enrollments.deleted_at')
            ->groupBy('courses.id')
            ->orderByDesc('courses.created_at');
    }

    public function getTeachingCourses(): EloquentCollection
    {
        return $this->getTeachingCoursesQuery()->get();
    }

    public function getTutoringCoursesQuery(): Builder
    {
        return Course::query()
            ->select('courses.*')
            ->selectRaw('COUNT(DISTINCT module_tutor_enrollments.module_id) as assigned_modules_count')
            ->join('modules', 'modules.belongsTo', '=', 'courses.id')
            ->join('module_tutor_enrollments', 'module_tutor_enrollments.module_id', '=', 'modules.id')
            ->where('module_tutor_enrollments.user_id', $this->getKey())
            ->whereNull('courses.deleted_at')
            ->whereNull('modules.deleted_at')
            ->whereNull('module_tutor_enrollments.deleted_at')
            ->groupBy('courses.id')
            ->orderByDesc('courses.created_at');
    }

    public function getTutoringCourses(): EloquentCollection
    {
        return $this->getTutoringCoursesQuery()->get();
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} {$this->surname}";
    }

    protected function fiscalCode(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): ?string => filled($value)
                ? Str::upper(trim((string) $value))
                : null,
        );
    }

    public function homeCountry(): BelongsTo
    {
        return $this->belongsTo(WorldCountry::class, 'home_country_id');
    }

    public function citizenshipCountry(): BelongsTo
    {
        return $this->belongsTo(WorldCountry::class, 'citizenship_country_id');
    }

    public function homeRegion(): BelongsTo
    {
        return $this->belongsTo(WorldDivision::class, 'home_region_id');
    }

    public function homeProvince(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'home_province_id');
    }

    public function homeCity(): BelongsTo
    {
        return $this->belongsTo(WorldCity::class, 'home_city_id');
    }

    /**
     * Get full home address attribute
     */
    public function getFullHomeAddressAttribute(): ?string
    {
        if (! $this->address) {
            return null;
        }

        return collect([
            $this->address,
            $this->postal_code ? "{$this->postal_code} {$this->homeCity?->name}" : $this->homeCity?->name,
            $this->homeProvince?->name,
            $this->homeRegion?->name,
            $this->homeCountry?->name,
        ])->filter()->implode(', ');
    }

    /**
     * Scope per filtrare utenti attivi
     */
    public function scopeActive($query)
    {
        return $query->where('account_state', UserStatus::ACTIVE->value);
    }

    /**
     * Scope per filtrare utenti stranieri o immigrati
     */
    public function scopeForeignerOrImmigrant($query)
    {
        return $query->where('is_foreigner_or_immigrant', true);
    }

    /**
     * Scope per filtrare utenti in onboarding
     */
    public function scopeNeedingOnboarding($query)
    {
        return $query->whereIn('account_state', [
            UserStatus::PENDING->value,
            UserStatus::ONBOARDING->value,
        ]);
    }

    /**
     * Check if user can access the platform
     */
    public function canAccessPlatform(): bool
    {
        return $this->account_state->canAccessPlatform();
    }

    /**
     * Check if user needs to complete onboarding
     */
    public function needsOnboarding(): bool
    {
        return $this->account_state->needsOnboarding();
    }

    /**
     * Check if user account is blocked
     */
    public function isBlocked(): bool
    {
        return $this->account_state->isBlocked();
    }

    /**
     * Check if user needs to update their data
     */
    public function needsDataUpdate(): bool
    {
        return $this->account_state === UserStatus::UPDATE_REQUIRED;
    }

    /**
     * Check if profile is completed
     */
    public function hasCompletedProfile(): bool
    {
        return $this->profile_completed_at !== null;
    }

    /**
     * Mark profile as completed
     */
    public function markProfileAsCompleted(): void
    {
        $this->update([
            'profile_completed_at' => now(),
            'account_state' => UserStatus::ACTIVE,
            'onboarding_step' => null,
        ]);
    }

    /**
     * Request data update
     */
    public function requestDataUpdate(): void
    {
        $this->update([
            'account_state' => UserStatus::UPDATE_REQUIRED,
            'last_data_update_request' => now(),
        ]);
    }

    /**
     * Mark data as updated
     */
    public function markDataAsUpdated(): void
    {
        $this->update([
            'account_state' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Suspend account
     */
    public function suspend(): void
    {
        $this->update(['account_state' => UserStatus::SUSPENDED]);
    }

    /**
     * Reactivate suspended account
     */
    public function reactivate(): void
    {
        $this->update(['account_state' => UserStatus::ACTIVE]);
    }

    /**
     * Move user to onboarding phase
     */
    public function moveToOnboarding(): void
    {
        $this->update([
            'account_state' => UserStatus::ONBOARDING,
            'onboarding_step' => OnboardingStep::PASSWORD_SETUP,
        ]);
    }

    /**
     * Advance to next onboarding step
     */
    public function advanceOnboardingStep(): void
    {
        if ($this->onboarding_step) {
            $nextStep = $this->onboarding_step->next();

            if ($nextStep) {
                $this->update(['onboarding_step' => $nextStep]);
            } else {
                // Last step completed
                $this->markProfileAsCompleted();
            }
        }
    }

    /**
     * Get current onboarding progress percentage
     */
    public function onboardingProgress(): int
    {
        return $this->onboarding_step?->progressPercentage() ?? 0;
    }

    /**
     * Accessor per job_country (ISO2) dalla jobUnit
     */
    public function getJobCountryAttribute(): ?string
    {
        return $this->jobUnit?->country?->code ?? null;
    }

    /**
     * Accessor per job_region (nome) dalla jobUnit
     */
    public function getJobRegionAttribute(): ?string
    {
        return $this->jobUnit?->region?->name ?? null;
    }

    /**
     * Accessor per job_province (sigla) dalla jobUnit
     */
    public function getJobProvinceAttribute(): ?string
    {
        return $this->jobUnit?->province?->code ?? null;
    }

    /**
     * Get the effective risk level for a worker in this sector with a specific job task.
     */
    public function getEffectiveWorkerRisk(): RiskLevel
    {
        return $this->getEffectiveWorkerRiskAt();
    }

    public function getEffectiveWorkerRiskAt(?CarbonInterface $referenceDate = null): RiskLevel
    {
        $activeJobTaskIds = $this->activeJobTasks($referenceDate)
            ->pluck('id')
            ->map(fn (mixed $jobTaskId): int => (int) $jobTaskId)
            ->all();

        if (! $this->hasRole('user') || ! $this->jobSector || $activeJobTaskIds === []) {
            throw new \LogicException('Cannot calculate risk level for user without "user" role, job sector or active job task');
        }

        return app(RiskCalculationService::class)
            ->getEffectiveWorkerRiskForTasks($this->jobSector->id, $activeJobTaskIds);
    }

    /**
     * @return Collection<int, JobTask>
     */
    public function activeJobTasks(?CarbonInterface $referenceDate = null): Collection
    {
        $evaluationDate = CarbonImmutable::instance($referenceDate ?? today());
        $jobTasks = $this->relationLoaded('jobTasks')
            ? $this->jobTasks
            : $this->jobTasks()->get();

        return $jobTasks
            ->filter(fn (JobTask $jobTask): bool => $this->jobTaskIsActiveOnDate($jobTask, $evaluationDate))
            ->values();
    }

    public function getCurrentJobTaskAttribute(): ?JobTask
    {
        return $this->activeJobTasks()
            ->sortByDesc(fn (JobTask $jobTask): string => (string) ($jobTask->pivot->starts_at ?? ''))
            ->first();
    }

    /**
     * @return Collection<int, array{
     *     effective_on: string,
     *     effective_on_label: string,
     *     risk_level: string,
     *     risk_label: string,
     *     risk_badge_class: string
     * }>
     */
    public function futureRiskTransitions(?CarbonInterface $referenceDate = null): Collection
    {
        $anchorDate = CarbonImmutable::instance($referenceDate ?? today());
        $jobTasks = $this->relationLoaded('jobTasks')
            ? $this->jobTasks
            : $this->jobTasks()->get();

        if (! $this->hasRole('user') || ! $this->jobSector || $jobTasks->isEmpty()) {
            return collect();
        }

        $candidateDates = $jobTasks
            ->flatMap(function (JobTask $jobTask) use ($anchorDate): array {
                $startsAt = $this->pivotDate($jobTask->pivot->starts_at ?? null);
                $endsAt = $this->pivotDate($jobTask->pivot->ends_at ?? null);
                $dates = [];

                if ($startsAt !== null && $startsAt->gt($anchorDate)) {
                    $dates[] = $startsAt;
                }

                if ($endsAt !== null && $endsAt->gte($anchorDate)) {
                    $dates[] = $endsAt->addDay();
                }

                return $dates;
            })
            ->filter()
            ->unique(fn (CarbonImmutable $date): string => $date->toDateString())
            ->sortBy(fn (CarbonImmutable $date): string => $date->toDateString())
            ->values();

        $currentRisk = rescue(
            fn (): RiskLevel => $this->getEffectiveWorkerRiskAt($anchorDate),
            report: false,
        );

        if (! $currentRisk instanceof RiskLevel) {
            return collect();
        }

        $lastRisk = $currentRisk;

        return $candidateDates
            ->map(function (CarbonImmutable $date) use (&$lastRisk): ?array {
                $futureRisk = rescue(
                    fn (): RiskLevel => $this->getEffectiveWorkerRiskAt($date),
                    report: false,
                );

                if (! $futureRisk instanceof RiskLevel || $futureRisk === $lastRisk) {
                    return null;
                }

                $lastRisk = $futureRisk;

                return [
                    'effective_on' => $date->toDateString(),
                    'effective_on_label' => $date->format('d/m/Y'),
                    'risk_level' => $futureRisk->value,
                    'risk_label' => $futureRisk->label(),
                    'risk_badge_class' => $futureRisk->badgeColor(),
                ];
            })
            ->filter()
            ->values();
    }

    private function jobTaskIsActiveOnDate(JobTask $jobTask, CarbonImmutable $referenceDate): bool
    {
        $startsAt = $this->pivotDate($jobTask->pivot->starts_at ?? null);
        $endsAt = $this->pivotDate($jobTask->pivot->ends_at ?? null);

        if ($startsAt === null || $startsAt->gt($referenceDate)) {
            return false;
        }

        return $endsAt === null || $endsAt->gte($referenceDate);
    }

    private function pivotDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }

    public function getRiskBasedRequirementsForEffectiveRisk(): array
    {
        $effectiveRisk = $this->getEffectiveWorkerRisk();

        if (! $effectiveRisk) {
            return [];
        }

        return app(RiskCalculationService::class)
            ->getRiskBasedRequirementsForRiskLevel($effectiveRisk);
    }

    /**
     * @return Collection<int, array{
     *     risk_based_requirement_id: int,
     *     risk_based_requirement_name: string,
     *     risk_based_requirement_description: ?string,
     *     satisfied: bool,
     *     status: string,
     *     status_label: string,
     *     required_course_validity_type: ?string,
     *     required_course_validity_type_label: ?string,
     *     expires_at: ?string,
     *     expires_at_label: ?string,
     *     certificate_ids: array<int, int>,
     *     certificate_names: array<int, string>
     * }>
     */
    public function checkRiskBasedRequirementsCompliance(): Collection
    {
        $riskBasedRequirements = collect($this->getRiskBasedRequirementsForEffectiveRisk())
            ->filter(fn (mixed $requirement): bool => $requirement instanceof RiskBasedRequirement)
            ->values();

        if ($riskBasedRequirements->isEmpty()) {
            return collect();
        }

        $courseRiskRequirementService = app(CourseRiskRequirementService::class);

        return $riskBasedRequirements->map(function (RiskBasedRequirement $riskBasedRequirement) use ($courseRiskRequirementService): array {
            $coverage = $courseRiskRequirementService->bestValidCertificateCoverageForRequirement($this, $riskBasedRequirement);
            $bestValidCertificate = $coverage['certificate'] ?? null;
            $coveringRequirement = $coverage['requirement'] ?? null;
            $matchingExpiredCertificates = $courseRiskRequirementService
                ->expiredCertificatesForRequirement($this, $riskBasedRequirement);
            $isSatisfied = $bestValidCertificate instanceof UserCertificate;
            $expiresAt = $bestValidCertificate?->expires_at;
            $status = $isSatisfied ? 'satisfied' : ($matchingExpiredCertificates->isNotEmpty() ? 'expired' : 'missing');
            $requiredCourseValidityType = in_array($status, ['missing', 'expired'], true)
                ? $courseRiskRequirementService->determineRequiredCourseValidityType($this, $riskBasedRequirement)
                : null;
            $coveringRiskLevel = $coveringRequirement instanceof RiskBasedRequirement
                ? $coveringRequirement->singleRiskLevel()
                : null;

            return [
                'risk_based_requirement_id' => (int) $riskBasedRequirement->getKey(),
                'risk_based_requirement_name' => $riskBasedRequirement->name,
                'risk_based_requirement_description' => $riskBasedRequirement->description,
                'satisfied' => $isSatisfied,
                'status' => $status,
                'status_label' => match ($status) {
                    'satisfied' => $expiresAt === null
                        ? __('Soddisfatto')
                        : __('Soddisfatto fino al :date', ['date' => $expiresAt->format('d/m/Y')]),
                    'expired' => __('Scaduto'),
                    default => __('Mancante'),
                },
                'required_course_validity_type' => $requiredCourseValidityType?->value,
                'required_course_validity_type_label' => $requiredCourseValidityType?->label(),
                'covered_by_higher_risk_certificate' => $coveringRiskLevel?->isHigherThan($riskBasedRequirement->singleRiskLevel() ?? $coveringRiskLevel) ?? false,
                'covering_risk_label' => $coveringRiskLevel?->label(),
                'expires_at' => $expiresAt?->toDateString(),
                'expires_at_label' => $expiresAt?->format('d/m/Y'),
                'certificate_ids' => $bestValidCertificate instanceof UserCertificate ? [(int) $bestValidCertificate->getKey()] : [],
                'certificate_names' => $bestValidCertificate instanceof UserCertificate ? [$bestValidCertificate->name] : [],
            ];
        })->values();
    }

    /**
     * @return Collection<int, array{
     *     requirement: RiskBasedRequirement,
     *     certificate: ?UserCertificate,
     *     issued_at_label: ?string,
     *     expires_at_label: ?string
     * }>
     */
    public function latestCertificatesByRequirementGroup(): Collection
    {
        $certificates = $this->userCertificates()
            ->with('riskBasedRequirements')
            ->orderByDesc('issued_at')
            ->get();

        return $certificates
            ->flatMap(function (UserCertificate $certificate): Collection {
                return $certificate->riskBasedRequirements
                    ->filter(fn (RiskBasedRequirement $requirement): bool => $requirement->exists)
                    ->map(fn (RiskBasedRequirement $requirement): array => [
                        'group_key' => $requirement->risk_progression_group ?: 'requirement:'.$requirement->getKey(),
                        'requirement' => $requirement,
                        'certificate' => $certificate,
                    ]);
            })
            ->groupBy('group_key')
            ->map(function (Collection $items): array {
                $latest = $items->sortByDesc(
                    fn (array $item): int => $item['certificate']->issued_at?->timestamp ?? 0
                )->first();

                /** @var RiskBasedRequirement $requirement */
                $requirement = $latest['requirement'];
                /** @var UserCertificate $certificate */
                $certificate = $latest['certificate'];

                return [
                    'requirement' => $requirement,
                    'certificate' => $certificate,
                    'issued_at_label' => $certificate->issued_at?->format('d/m/Y'),
                    'expires_at_label' => $certificate->expires_at?->format('d/m/Y'),
                ];
            })
            ->values();
    }

    public function hasVerifiedLanguageLevelFor(?LanguageLevel $requiredLanguageLevel): bool
    {
        if ($requiredLanguageLevel === null) {
            return true;
        }

        $this->loadMissing('verifiedLanguageLevel');

        return $this->verifiedLanguageLevel !== null
            && $this->verifiedLanguageLevel->sort_order >= $requiredLanguageLevel->sort_order;
    }

    public function maskedEmail(): ?string
    {
        if (! is_string($this->email) || trim($this->email) === '' || ! str_contains($this->email, '@')) {
            return null;
        }

        [$localPart, $domain] = explode('@', $this->email, 2);
        $visiblePrefix = mb_substr($localPart, 0, 1);
        $maskedLocalPart = $visiblePrefix.str_repeat('*', max(2, mb_strlen($localPart) - 1));

        return $maskedLocalPart.'@'.$domain;
    }
}
