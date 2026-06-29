<?php

namespace App\Services;

use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\RiskBasedRequirement;
use App\Models\User;
use App\Models\UserCertificate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CourseRiskRequirementService
{
    public function determineRequiredValidityType(
        User $user,
        RiskBasedRequirement $riskBasedRequirement,
        ?CarbonInterface $referenceDate = null,
    ): CourseRiskRequirementValidityType {
        $referenceDate ??= now();

        $relevantCertificates = $this->certificatesDirectlyMatchingRequirement($user, $riskBasedRequirement);

        if ($relevantCertificates->isEmpty()) {
            return CourseRiskRequirementValidityType::FirstAchievement;
        }

        $validCertificateExists = $relevantCertificates->contains(
            fn (UserCertificate $certificate): bool => $this->isCertificateValidOn($certificate, $referenceDate)
        );

        if ($validCertificateExists) {
            return CourseRiskRequirementValidityType::Refresh;
        }

        if (! $riskBasedRequirement->hasFormationResetWindow()) {
            return CourseRiskRequirementValidityType::Refresh;
        }

        $latestCertificate = $relevantCertificates->sortByDesc('issued_at')->first();

        if ($latestCertificate === null || $latestCertificate->issued_at === null) {
            return CourseRiskRequirementValidityType::FirstAchievement;
        }

        $resetReferenceDate = $latestCertificate->expires_at?->copy()
            ?? $latestCertificate->issued_at->copy();
        $resetDeadline = $resetReferenceDate
            ->addYearsNoOverflow($riskBasedRequirement->reset_formation_years);

        return $referenceDate->greaterThan($resetDeadline)
            ? CourseRiskRequirementValidityType::FirstAchievement
            : CourseRiskRequirementValidityType::Refresh;
    }

    public function determineRequiredCourseValidityType(
        User $user,
        RiskBasedRequirement $riskBasedRequirement,
        ?CarbonInterface $referenceDate = null,
    ): CourseRiskRequirementValidityType {
        $referenceDate ??= now();

        if (! $riskBasedRequirement->supportsRiskProgressionRules()) {
            return $this->determineRequiredValidityType($user, $riskBasedRequirement, $referenceDate);
        }

        $targetRiskLevel = $riskBasedRequirement->singleRiskLevel();

        if (! $targetRiskLevel instanceof RiskLevel) {
            return $this->determineRequiredValidityType($user, $riskBasedRequirement, $referenceDate);
        }

        $bestValidCoverage = $this->bestValidCertificateCoverageAcrossFamily($user, $riskBasedRequirement, $referenceDate);
        $bestValidRiskLevel = $bestValidCoverage['requirement']?->singleRiskLevel();

        if ($bestValidRiskLevel instanceof RiskLevel) {
            if ($bestValidRiskLevel->isHigherThan($targetRiskLevel)) {
                return CourseRiskRequirementValidityType::Refresh;
            }

            if ($bestValidRiskLevel->isLowerThan($targetRiskLevel)) {
                return CourseRiskRequirementValidityType::Integrative;
            }

            return CourseRiskRequirementValidityType::Refresh;
        }

        $latestCoverage = $this->latestCertificateCoverageAcrossFamily($user, $riskBasedRequirement);
        $latestRiskLevel = $latestCoverage['requirement']?->singleRiskLevel();

        if ($latestRiskLevel instanceof RiskLevel && $latestRiskLevel->isAtLeast($targetRiskLevel)) {
            return CourseRiskRequirementValidityType::Refresh;
        }

        return $this->determineRequiredValidityType($user, $riskBasedRequirement, $referenceDate);
    }

    public function courseRequirementMatchesUserNeed(
        User $user,
        RiskBasedRequirement $riskBasedRequirement,
        CourseRiskRequirementValidityType|array|string|null $courseValidityTypes,
        ?CarbonInterface $referenceDate = null,
    ): bool {
        $normalizedCourseValidityTypes = CourseRiskRequirementValidityType::normalizeMany(
            is_array($courseValidityTypes)
                ? $courseValidityTypes
                : [$courseValidityTypes]
        );

        $requiredType = $this->determineRequiredCourseValidityType($user, $riskBasedRequirement, $referenceDate);

        return collect($normalizedCourseValidityTypes)
            ->contains(fn (CourseRiskRequirementValidityType $courseValidityType): bool => $courseValidityType === $requiredType);
    }

    /**
     * @return array{certificate: ?UserCertificate, requirement: ?RiskBasedRequirement}
     */
    public function bestValidCertificateCoverageForRequirement(
        User $user,
        RiskBasedRequirement $riskBasedRequirement,
        ?CarbonInterface $referenceDate = null,
    ): array {
        $referenceDate ??= now();
        $familyRequirements = $this->requirementFamily($riskBasedRequirement);
        $targetRiskLevel = $riskBasedRequirement->singleRiskLevel();

        $bestCoverage = $this->bestCertificateCoverage(
            $this->certificatesForRequirementFamily($user, $familyRequirements)
                ->filter(fn (UserCertificate $certificate): bool => $this->isCertificateValidOn($certificate, $referenceDate))
                ->values(),
            $familyRequirements,
        );

        $coveredRiskLevel = $bestCoverage['requirement']?->singleRiskLevel();

        if ($targetRiskLevel instanceof RiskLevel && $coveredRiskLevel instanceof RiskLevel && $coveredRiskLevel->isLowerThan($targetRiskLevel)) {
            return ['certificate' => null, 'requirement' => null];
        }

        return $bestCoverage;
    }

    /**
     * @return Collection<int, UserCertificate>
     */
    public function expiredCertificatesForRequirement(
        User $user,
        RiskBasedRequirement $riskBasedRequirement,
        ?CarbonInterface $referenceDate = null,
    ): Collection {
        $referenceDate ??= now();
        $familyRequirements = $this->requirementFamily($riskBasedRequirement);

        return $this->certificatesForRequirementFamily($user, $familyRequirements)
            ->reject(fn (UserCertificate $certificate): bool => $this->isCertificateValidOn($certificate, $referenceDate))
            ->values();
    }

    /**
     * @return Collection<int, UserCertificate>
     */
    public function syncCertificatesForEnrollment(CourseEnrollment $courseEnrollment): Collection
    {
        $courseEnrollment->loadMissing([
            'course.riskBasedRequirements',
            'user',
        ]);

        $course = $courseEnrollment->course;
        $user = $courseEnrollment->user;

        if ($course === null || $user === null) {
            return collect();
        }

        // Se il corso non ha requisiti di rischio, non genera certificati
        if ($course->riskBasedRequirements->isEmpty()) {
            $courseEnrollment->forceFill([
                'certificate_generation_error' => null,
            ])->save();

            return collect();
        }

        $issuedAt = ($courseEnrollment->completed_at ?? now())->copy()->startOfDay();

        return $course->riskBasedRequirements
            ->map(function (RiskBasedRequirement $riskBasedRequirement) use ($course, $user, $courseEnrollment, $issuedAt): ?array {
                // Determinare il tipo di validità richiesto per l'utente
                $courseValidityType = $courseEnrollment->course_validity_type !== null
                    ? CourseRiskRequirementValidityType::from($courseEnrollment->course_validity_type)
                    : $this->determineRequiredCourseValidityType($user, $riskBasedRequirement, $issuedAt);
                $courseValidityTypes = $course->courseValidityTypesForRequirement($riskBasedRequirement);

                // 1. Verificare se il tipo di validità richiesto è supportato dal corso
                if (! $courseValidityTypes->contains($courseValidityType)) {
                    return [
                        'certificate' => null,
                        'requirement' => $riskBasedRequirement,
                        'error' => 'course_does_not_match_need',
                    ];
                }

                // 2. Se è un corso integrativo, verificare i prerequisiti di partecipazione
                if ($courseValidityType === CourseRiskRequirementValidityType::Integrative) {
                    $allowedStartRiskLevels = $course->integrativeStartRiskLevelsForRequirement($riskBasedRequirement);

                    if ($allowedStartRiskLevels->isEmpty()) {
                        return [
                            'certificate' => null,
                            'requirement' => $riskBasedRequirement,
                            'error' => 'integrative_prerequisites_not_met',
                        ];
                    }

                    $familyRequirements = $this->requirementFamily($riskBasedRequirement);
                    $validCertificates = $this->certificatesForRequirementFamily($user, $familyRequirements)
                        ->filter(fn (UserCertificate $certificate): bool => $this->isCertificateValidOn($certificate, $issuedAt))
                        ->all();

                    // Verificare se l'utente ha un certificato valido con uno dei livelli di rischio richiesti
                    $hasValidPrerequisiteCertificate = false;
                    foreach ($validCertificates as $certificate) {
                        foreach ($certificate->riskBasedRequirements as $attachedRequirement) {
                            $certificateRiskLevel = $attachedRequirement->singleRiskLevel();
                            if ($certificateRiskLevel instanceof RiskLevel
                                && $allowedStartRiskLevels->contains(
                                    fn (RiskLevel $allowedRiskLevel): bool => $allowedRiskLevel === $certificateRiskLevel
                                )) {
                                $hasValidPrerequisiteCertificate = true;
                                break 2;
                            }
                        }
                    }

                    if (! $hasValidPrerequisiteCertificate) {
                        // Verificare se esiste un certificato scaduto
                        $expiredCertificates = $this->certificatesForRequirementFamily($user, $familyRequirements)
                            ->reject(fn (UserCertificate $certificate): bool => $this->isCertificateValidOn($certificate, $issuedAt))
                            ->all();

                        $hasExpiredPrerequisiteCertificate = false;
                        foreach ($expiredCertificates as $certificate) {
                            foreach ($certificate->riskBasedRequirements as $attachedRequirement) {
                                $certificateRiskLevel = $attachedRequirement->singleRiskLevel();
                                if ($certificateRiskLevel instanceof RiskLevel
                                    && $allowedStartRiskLevels->contains(
                                        fn (RiskLevel $allowedRiskLevel): bool => $allowedRiskLevel === $certificateRiskLevel
                                    )) {
                                    $hasExpiredPrerequisiteCertificate = true;
                                    break 2;
                                }
                            }
                        }

                        $errorCode = $hasExpiredPrerequisiteCertificate ? 'participation_certificate_expired' : 'integrative_prerequisites_not_met';

                        return [
                            'certificate' => null,
                            'requirement' => $riskBasedRequirement,
                            'error' => $errorCode,
                        ];
                    }
                }

                // 3. Verificare se esiste già un certificato valido per questo corso e requisito
                if ($this->hasValidCertificateForCourseRequirement($user, $course, $riskBasedRequirement, $issuedAt)) {
                    return null;
                }

                // 4. Generare il certificato
                $description = $this->generateCertificateDescription($course, $courseValidityType, $courseEnrollment);

                $certificate = $user->userCertificates()->create([
                    'internal_course_id' => $course->getKey(),
                    'name' => $course->title ?: 'Corso interno',
                    'description' => $description,
                    'is_internal' => true,
                    'issued_at' => $issuedAt,
                    'expires_at' => $riskBasedRequirement->hasLimitedValidity() && $riskBasedRequirement->validity_months !== null
                        ? $issuedAt->copy()->addMonthsNoOverflow($riskBasedRequirement->validity_months)
                        : null,
                ]);

                $certificate->riskBasedRequirements()->attach($riskBasedRequirement->getKey());

                return [
                    'certificate' => $certificate,
                    'requirement' => $riskBasedRequirement,
                    'error' => null,
                ];
            })
            ->each(function (?array $result) use ($courseEnrollment): void {
                if ($result === null) {
                    return;
                }

                if ($result['error'] !== null) {
                    $this->updateEnrollmentWithError($courseEnrollment, $result['requirement'], $result['error']);
                } else {
                    $courseEnrollment->forceFill([
                        'certificate_generation_error' => null,
                    ])->save();
                }
            })
            ->filter(fn (?array $result): bool => $result !== null && $result['certificate'] instanceof UserCertificate)
            ->map(fn (array $result): UserCertificate => $result['certificate'])
            ->values();
    }

    /**
     * @return Collection<int, CourseEnrollment>
     */
    public function syncCertificatesForCompletedEnrollments(): Collection
    {
        $processedEnrollments = collect();

        CourseEnrollment::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('status', CourseEnrollment::STATUS_COMPLETED)
                    ->orWhereNotNull('completed_at');
            })
            ->whereHas('course.riskBasedRequirements')
            ->with(['course.riskBasedRequirements', 'user'])
            ->orderBy('id')
            ->cursor()
            ->each(function (CourseEnrollment $courseEnrollment) use ($processedEnrollments): void {
                $this->syncCertificatesForEnrollment($courseEnrollment);
                $processedEnrollments->push($courseEnrollment);
            });

        return $processedEnrollments;
    }

    public function userCanEnrollInCourse(User $user, Course $course, ?CarbonInterface $referenceDate = null): bool
    {
        $referenceDate ??= now();
        $course->loadMissing('riskBasedRequirements');

        return $course->riskBasedRequirements->every(function (RiskBasedRequirement $requirement) use ($course, $referenceDate, $user): bool {
            return $this->courseRequirementMatchesUserNeed(
                $user,
                $requirement,
                $course->courseValidityTypesForRequirement($requirement)->all(),
                $referenceDate,
            ) && $this->userSatisfiesCoursePrerequisiteAtDate($user, $course, $requirement, $referenceDate);
        });
    }

    /**
     * @return array<int, array{
     *     requirement_name: string,
     *     required_validity_type: CourseRiskRequirementValidityType,
     *     supported_validity_types: array<int, CourseRiskRequirementValidityType>,
     *     allowed_start_risk_levels: array<int, RiskLevel>,
     *     reason: string,
     *     message: string
     * }>
     */
    public function enrollmentRequirementIssues(
        User $user,
        Course $course,
        ?CarbonInterface $referenceDate = null,
    ): array {
        $referenceDate ??= now();
        $course->loadMissing('riskBasedRequirements');

        return $course->riskBasedRequirements
            ->map(function (RiskBasedRequirement $riskBasedRequirement) use ($course, $referenceDate, $user): ?array {
                $supportedValidityTypes = $course->courseValidityTypesForRequirement($riskBasedRequirement)->all();
                $requiredValidityType = $this->determineRequiredCourseValidityType($user, $riskBasedRequirement, $referenceDate);
                $allowedStartRiskLevels = $course->integrativeStartRiskLevelsForRequirement($riskBasedRequirement)->all();

                if (! in_array($requiredValidityType, $supportedValidityTypes, true)) {
                    return [
                        'requirement_name' => $riskBasedRequirement->name,
                        'required_validity_type' => $requiredValidityType,
                        'supported_validity_types' => $supportedValidityTypes,
                        'allowed_start_risk_levels' => $allowedStartRiskLevels,
                        'reason' => 'validity_mismatch',
                        'message' => $this->formatValidityMismatchMessage(
                            $riskBasedRequirement,
                            $requiredValidityType,
                            $supportedValidityTypes,
                        ),
                    ];
                }

                if (! $this->userSatisfiesCoursePrerequisiteAtDate($user, $course, $riskBasedRequirement, $referenceDate)) {
                    return [
                        'requirement_name' => $riskBasedRequirement->name,
                        'required_validity_type' => $requiredValidityType,
                        'supported_validity_types' => $supportedValidityTypes,
                        'allowed_start_risk_levels' => $allowedStartRiskLevels,
                        'reason' => 'missing_prerequisite',
                        'message' => $this->formatMissingPrerequisiteMessage(
                            $user,
                            $riskBasedRequirement,
                            $allowedStartRiskLevels,
                            $referenceDate,
                        ),
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    public function enrollmentEligibilityMessage(
        User $user,
        Course $course,
        ?CarbonInterface $referenceDate = null,
    ): ?string {
        $issues = $this->enrollmentRequirementIssues($user, $course, $referenceDate);

        if ($issues === []) {
            return null;
        }

        $details = collect($issues)
            ->pluck('message')
            ->map(static fn (string $message): string => Str::finish($message, '.'))
            ->implode(' ');

        return __('L\'utente non possiede i prerequisiti necessari per l\'iscrizione a questo corso. Requisiti mancanti: :details', [
            'details' => $details,
        ]);
    }

    public function userSatisfiesCoursePrerequisiteAtDate(
        User $user,
        Course $course,
        RiskBasedRequirement $riskBasedRequirement,
        CarbonInterface $referenceDate,
    ): bool {
        $requiredCourseValidityType = $this->determineRequiredCourseValidityType($user, $riskBasedRequirement, $referenceDate);

        if ($requiredCourseValidityType !== CourseRiskRequirementValidityType::Integrative) {
            return true;
        }

        if (! $course->courseHasValidityTypeForRequirement($riskBasedRequirement, CourseRiskRequirementValidityType::Integrative)) {
            return false;
        }

        $allowedStartRiskLevels = $course->integrativeStartRiskLevelsForRequirement($riskBasedRequirement);

        if ($allowedStartRiskLevels->isEmpty()) {
            return false;
        }

        $familyRequirements = $this->requirementFamily($riskBasedRequirement);

        return $this->certificatesForRequirementFamily($user, $familyRequirements)
            ->filter(fn (UserCertificate $certificate): bool => $this->isCertificateValidOn($certificate, $referenceDate))
            ->contains(function (UserCertificate $certificate) use ($allowedStartRiskLevels): bool {
                return $certificate->riskBasedRequirements
                    ->contains(function (RiskBasedRequirement $attachedRequirement) use ($allowedStartRiskLevels): bool {
                        $certificateRiskLevel = $attachedRequirement->singleRiskLevel();

                        return $certificateRiskLevel instanceof RiskLevel
                            && $allowedStartRiskLevels->contains(
                                fn (RiskLevel $allowedRiskLevel): bool => $allowedRiskLevel === $certificateRiskLevel
                            );
                    });
            });
    }

    /**
     * @return Collection<int, UserCertificate>
     */
    private function certificatesDirectlyMatchingRequirement(User $user, RiskBasedRequirement $riskBasedRequirement): Collection
    {
        return $user->userCertificates()
            ->whereHas('riskBasedRequirements', function (Builder $query) use ($riskBasedRequirement): void {
                $query->whereKey($riskBasedRequirement->getKey());
            })
            ->orderByDesc('issued_at')
            ->get();
    }

    /**
     * @return Collection<int, RiskBasedRequirement>
     */
    private function requirementFamily(RiskBasedRequirement $riskBasedRequirement): Collection
    {
        if (! $riskBasedRequirement->supportsRiskProgressionRules()) {
            return collect([$riskBasedRequirement]);
        }

        // Group progression applies only within the same training family, not across
        // every requirement that happens to share the same low/medium/high ordering.
        return RiskBasedRequirement::query()
            ->where('risk_progression_group', $riskBasedRequirement->risk_progression_group)
            ->get()
            ->filter(fn (RiskBasedRequirement $requirement): bool => $requirement->isRiskSpecific())
            ->values();
    }

    /**
     * @return array{certificate: ?UserCertificate, requirement: ?RiskBasedRequirement}
     */
    private function bestValidCertificateCoverageAcrossFamily(
        User $user,
        RiskBasedRequirement $riskBasedRequirement,
        CarbonInterface $referenceDate,
    ): array {
        $familyRequirements = $this->requirementFamily($riskBasedRequirement);

        return $this->bestCertificateCoverage(
            $this->certificatesForRequirementFamily($user, $familyRequirements)
                ->filter(fn (UserCertificate $certificate): bool => $this->isCertificateValidOn($certificate, $referenceDate))
                ->values(),
            $familyRequirements,
        );
    }

    /**
     * @return array{certificate: ?UserCertificate, requirement: ?RiskBasedRequirement}
     */
    private function latestCertificateCoverageAcrossFamily(User $user, RiskBasedRequirement $riskBasedRequirement): array
    {
        $familyRequirements = $this->requirementFamily($riskBasedRequirement);

        return $this->bestCertificateCoverage(
            $this->certificatesForRequirementFamily($user, $familyRequirements),
            $familyRequirements,
        );
    }

    /**
     * @param  Collection<int, RiskBasedRequirement>  $familyRequirements
     * @return Collection<int, UserCertificate>
     */
    private function certificatesForRequirementFamily(User $user, Collection $familyRequirements): Collection
    {
        $familyRequirementIds = $familyRequirements->pluck('id')->all();

        return $user->userCertificates()
            ->with('riskBasedRequirements')
            ->whereHas('riskBasedRequirements', function (Builder $query) use ($familyRequirementIds): void {
                $query->whereIn('risk_based_requirements.id', $familyRequirementIds);
            })
            ->orderByDesc('issued_at')
            ->get();
    }

    /**
     * @param  Collection<int, UserCertificate>  $certificates
     * @param  Collection<int, RiskBasedRequirement>  $familyRequirements
     * @return array{certificate: ?UserCertificate, requirement: ?RiskBasedRequirement}
     */
    private function bestCertificateCoverage(Collection $certificates, Collection $familyRequirements): array
    {
        $familyRequirementMap = $familyRequirements->keyBy('id');

        $bestCertificate = null;
        $bestRequirement = null;
        $bestRiskLevel = null;

        foreach ($certificates as $certificate) {
            $matchedRequirement = $certificate->riskBasedRequirements
                ->map(fn (RiskBasedRequirement $requirement): ?RiskBasedRequirement => $familyRequirementMap->get($requirement->getKey()))
                ->filter()
                ->sortByDesc(fn (RiskBasedRequirement $requirement): int => $requirement->singleRiskLevel()?->order() ?? 0)
                ->first();

            if (! $matchedRequirement instanceof RiskBasedRequirement) {
                continue;
            }

            $matchedRiskLevel = $matchedRequirement->singleRiskLevel();
            $bestCertificateRiskLevel = $bestRequirement instanceof RiskBasedRequirement
                ? $bestRequirement->singleRiskLevel()
                : null;

            $currentExpiresTimestamp = $certificate->expires_at?->timestamp ?? PHP_INT_MAX;
            $bestExpiresTimestamp = $bestCertificate?->expires_at?->timestamp ?? PHP_INT_MIN;
            $currentIssuedTimestamp = $certificate->issued_at?->timestamp ?? PHP_INT_MIN;
            $bestIssuedTimestamp = $bestCertificate?->issued_at?->timestamp ?? PHP_INT_MIN;

            if (
                ! $bestRequirement instanceof RiskBasedRequirement
                || (
                    $matchedRiskLevel instanceof RiskLevel
                    && ! $bestCertificateRiskLevel instanceof RiskLevel
                )
                || (
                    $matchedRiskLevel instanceof RiskLevel
                    && $bestCertificateRiskLevel instanceof RiskLevel
                    && (
                        $matchedRiskLevel->isHigherThan($bestCertificateRiskLevel)
                        || (
                            $matchedRiskLevel === $bestCertificateRiskLevel
                            && (
                                $currentExpiresTimestamp > $bestExpiresTimestamp
                                || ($currentExpiresTimestamp === $bestExpiresTimestamp && $currentIssuedTimestamp > $bestIssuedTimestamp)
                            )
                        )
                    )
                )
                || (
                    ! $matchedRiskLevel instanceof RiskLevel
                    && ! $bestCertificateRiskLevel instanceof RiskLevel
                    && (
                        $currentExpiresTimestamp > $bestExpiresTimestamp
                        || ($currentExpiresTimestamp === $bestExpiresTimestamp && $currentIssuedTimestamp > $bestIssuedTimestamp)
                    )
                )
            ) {
                $bestCertificate = $certificate;
                $bestRequirement = $matchedRequirement;
                $bestRiskLevel = $matchedRiskLevel;
            }
        }

        return [
            'certificate' => $bestCertificate,
            'requirement' => $bestRequirement,
        ];
    }

    private function hasValidCertificateForCourseRequirement(
        User $user,
        Course $course,
        RiskBasedRequirement $riskBasedRequirement,
        CarbonInterface $referenceDate,
    ): bool {
        return $user->userCertificates()
            ->where('internal_course_id', $course->getKey())
            ->whereHas('riskBasedRequirements', function (Builder $query) use ($riskBasedRequirement): void {
                $query->whereKey($riskBasedRequirement->getKey());
            })
            ->get()
            ->contains(fn (UserCertificate $certificate): bool => $this->isCertificateValidOn($certificate, $referenceDate));
    }

    private function isCertificateValidOn(UserCertificate $certificate, CarbonInterface $referenceDate): bool
    {
        return $certificate->expires_at === null
            || $certificate->expires_at->greaterThanOrEqualTo($referenceDate->copy()->startOfDay());
    }

    private function generateCertificateDescription(
        Course $course,
        CourseRiskRequirementValidityType $courseValidityType,
        CourseEnrollment $courseEnrollment,
    ): string {
        $baseDescription = sprintf(
            'Conseguito partecipando al corso interno "%s" (%s)',
            $course->title ?: 'Corso interno',
            $course->getKey(),
        );

        $validityTypeLabel = match ($courseValidityType) {
            CourseRiskRequirementValidityType::FirstAchievement => 'conseguimento iniziale',
            CourseRiskRequirementValidityType::Refresh => 'aggiornamento periodico',
            CourseRiskRequirementValidityType::Integrative => 'corso integrativo',
        };

        $description = $baseDescription.' come '.$validityTypeLabel;

        if ($courseEnrollment->is_integrative_enrollment) {
            $description .= ' (modalità integrativa)';
        }

        return $description;
    }

    private function updateEnrollmentWithError(
        CourseEnrollment $courseEnrollment,
        RiskBasedRequirement $riskBasedRequirement,
        string $errorCode,
    ): void {
        $errorMessages = [
            'course_does_not_match_need' => sprintf(
                'Il corso non corrisponde al fabbisogno dell\'utente per il requisito "%s".',
                $riskBasedRequirement->name,
            ),
            'integrative_prerequisites_not_met' => sprintf(
                'L\'utente non possiede il certificato valido richiesto per partecipare al corso come corso integrativo per il requisito "%s".',
                $riskBasedRequirement->name,
            ),
            'participation_certificate_expired' => sprintf(
                'Il certificato richiesto per la partecipazione del corso integrativo per il requisito "%s" è scaduto.',
                $riskBasedRequirement->name,
            ),
        ];

        $errorMessage = $errorMessages[$errorCode] ?? 'Errore sconosciuto durante la generazione del certificato.';

        $courseEnrollment->forceFill([
            'certificate_generation_error' => $errorCode,
        ])->save();
    }

    /**
     * @param  array<int, CourseRiskRequirementValidityType>  $supportedValidityTypes
     */
    private function formatValidityMismatchMessage(
        RiskBasedRequirement $riskBasedRequirement,
        CourseRiskRequirementValidityType $requiredValidityType,
        array $supportedValidityTypes,
    ): string {
        $supportedLabels = CourseRiskRequirementValidityType::labelsText($supportedValidityTypes);

        return sprintf(
            '%s: il corso copre %s, ma per questo utente serve %s',
            $riskBasedRequirement->name,
            $supportedLabels !== '' ? $supportedLabels : __('nessuna validità configurata'),
            $requiredValidityType->label(),
        );
    }

    /**
     * @param  array<int, RiskLevel>  $allowedStartRiskLevels
     */
    private function formatMissingPrerequisiteMessage(
        User $user,
        RiskBasedRequirement $riskBasedRequirement,
        array $allowedStartRiskLevels,
        CarbonInterface $referenceDate,
    ): string {
        if ($allowedStartRiskLevels === []) {
            return sprintf(
                '%s: il corso integrativo non ha livelli iniziali ammessi configurati',
                $riskBasedRequirement->name,
            );
        }

        $allowedRiskLevelLabels = collect($allowedStartRiskLevels)
            ->map(static fn (RiskLevel $riskLevel): string => $riskLevel->label())
            ->implode(', ');

        $hasExpiredAllowedCertificate = $this->expiredCertificatesForRequirement($user, $riskBasedRequirement, $referenceDate)
            ->contains(function (UserCertificate $certificate) use ($allowedStartRiskLevels): bool {
                return $certificate->riskBasedRequirements
                    ->contains(function (RiskBasedRequirement $attachedRequirement) use ($allowedStartRiskLevels): bool {
                        $certificateRiskLevel = $attachedRequirement->singleRiskLevel();

                        return $certificateRiskLevel instanceof RiskLevel
                            && in_array($certificateRiskLevel, $allowedStartRiskLevels, true);
                    });
            });

        if ($hasExpiredAllowedCertificate) {
            return sprintf(
                '%s: serve un attestato di partenza valido tra %s, ma quello disponibile risulta scaduto',
                $riskBasedRequirement->name,
                $allowedRiskLevelLabels,
            );
        }

        return sprintf(
            '%s: serve un attestato di partenza valido tra %s',
            $riskBasedRequirement->name,
            $allowedRiskLevelLabels,
        );
    }
}
