<?php

namespace App\Services;

use App\Enums\CourseRiskRequirementValidityType;
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

        $relevantCertificates = $user->userCertificates()
            ->whereHas('riskBasedRequirements', function (Builder $query) use ($riskBasedRequirement): void {
                $query->whereKey($riskBasedRequirement->getKey());
            })
            ->orderByDesc('issued_at')
            ->get();

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

    public function courseRequirementMatchesUserNeed(
        User $user,
        RiskBasedRequirement $riskBasedRequirement,
        CourseRiskRequirementValidityType|string $courseValidityType,
        ?CarbonInterface $referenceDate = null,
    ): bool {
        $normalizedCourseValidityType = $courseValidityType instanceof CourseRiskRequirementValidityType
            ? $courseValidityType
            : (CourseRiskRequirementValidityType::tryFrom($courseValidityType)
                ?? CourseRiskRequirementValidityType::Both);

        return $normalizedCourseValidityType->matchesRequirementNeed(
            $this->determineRequiredValidityType($user, $riskBasedRequirement, $referenceDate)
        );
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
                return $this->courseRequirementMatchesUserNeed(
                    $user,
                    $riskBasedRequirement,
                    $course->courseValidityTypeForRequirement($riskBasedRequirement),
                    $issuedAt,
                );
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
