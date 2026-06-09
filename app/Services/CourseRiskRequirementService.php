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

        $issuedAt = ($courseEnrollment->completed_at ?? now())->copy()->startOfDay();

        return $course->riskBasedRequirements
            ->filter(function (RiskBasedRequirement $riskBasedRequirement) use ($course, $user, $issuedAt): bool {
                if (! $this->courseRequirementMatchesUserNeed(
                    $user,
                    $riskBasedRequirement,
                    $course->courseValidityTypesForRequirement($riskBasedRequirement)->all(),
                    $issuedAt,
                )) {
                    return false;
                }

                return $this->userSatisfiesCoursePrerequisiteAtDate($user, $course, $riskBasedRequirement, $issuedAt);
            })
            ->map(function (RiskBasedRequirement $riskBasedRequirement) use ($course, $user, $issuedAt): ?UserCertificate {
                if ($this->hasValidCertificateForCourseRequirement($user, $course, $riskBasedRequirement, $issuedAt)) {
                    return null;
                }

                $certificate = $user->userCertificates()->create([
                    'internal_course_id' => $course->getKey(),
                    'name' => $course->title ?: 'Corso interno',
                    'description' => sprintf(
                        'Corso %s (%s) che risponde al requisito %s',
                        $course->getKey(),
                        $course->title ?: 'Corso interno',
                        $riskBasedRequirement->name,
                    ),
                    'is_internal' => true,
                    'issued_at' => $issuedAt,
                    'expires_at' => $riskBasedRequirement->hasLimitedValidity() && $riskBasedRequirement->validity_months !== null
                        ? $issuedAt->copy()->addMonthsNoOverflow($riskBasedRequirement->validity_months)
                        : null,
                ]);

                $certificate->riskBasedRequirements()->attach($riskBasedRequirement->getKey());

                return $certificate;
            })
            ->filter(fn (?UserCertificate $certificate): bool => $certificate instanceof UserCertificate)
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

            if (! $matchedRiskLevel instanceof RiskLevel) {
                continue;
            }

            if (
                ! $bestRiskLevel instanceof RiskLevel
                || $matchedRiskLevel->isHigherThan($bestRiskLevel)
                || (
                    $matchedRiskLevel === $bestRiskLevel
                    && ($certificate->expires_at?->timestamp ?? PHP_INT_MAX) > ($bestCertificate?->expires_at?->timestamp ?? PHP_INT_MIN)
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
}
