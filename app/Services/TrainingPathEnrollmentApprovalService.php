<?php

namespace App\Services;

use App\Models\Importazione;
use App\Models\TrainingPath;
use App\Models\TrainingPathCourseApproval;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainingPathEnrollmentApprovalService
{
    public function __construct(
        private readonly CourseRiskRequirementService $courseRiskRequirementService,
        private readonly TrainingPathEnrollmentSyncService $trainingPathEnrollmentSyncService,
    ) {}

    /**
     * @return array<int, array{course_id: int, course_title: string, reasons: array<int, string>}>
     */
    public function courseIssuesFor(User $user, TrainingPath $trainingPath): array
    {
        $trainingPath->loadMissing([
            'courses',
            'courses.jobRoles',
            'courses.jobTasks',
            'courses.jobUnits',
        ]);

        return $trainingPath->courses
            ->where('status', 'published')
            ->map(function ($course) use ($user): ?array {
                $reasons = collect([
                    $course->enrollmentVisibilityMessageFor($user),
                    $this->courseRiskRequirementService->enrollmentEligibilityMessage($user, $course),
                ])->filter()->unique()->values()->all();

                if ($reasons === []) {
                    return null;
                }

                return [
                    'course_id' => (int) $course->getKey(),
                    'course_title' => (string) $course->title,
                    'reasons' => $reasons,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function enrollEligible(User $user, TrainingPath $trainingPath): TrainingPathEnrollment
    {
        if ($this->courseIssuesFor($user, $trainingPath) !== []) {
            throw new DomainException('Training path course approval is required.');
        }

        return $this->persistEnrollment($user, $trainingPath, false);
    }

    public function approveAndEnroll(
        User $user,
        TrainingPath $trainingPath,
        User $reviewer,
        ?Importazione $importazione = null,
    ): TrainingPathEnrollment {
        return DB::transaction(function () use ($user, $trainingPath, $reviewer, $importazione): TrainingPathEnrollment {
            foreach ($this->courseIssuesFor($user, $trainingPath) as $issue) {
                TrainingPathCourseApproval::query()->create([
                    'importazione_id' => $importazione?->getKey(),
                    'user_id' => $user->getKey(),
                    'training_path_id' => $trainingPath->getKey(),
                    'course_id' => $issue['course_id'],
                    'status' => TrainingPathCourseApproval::STATUS_APPROVED,
                    'reasons' => $issue['reasons'],
                    'reviewed_by' => $reviewer->getKey(),
                    'reviewed_at' => now(),
                ]);
            }

            return $this->persistEnrollment($user, $trainingPath, true);
        });
    }

    /**
     * @param  array<int, array{course_id: int, course_title: string, reasons: array<int, string>}>  $issues
     */
    public function queueImportApprovals(
        Importazione $importazione,
        User $user,
        TrainingPath $trainingPath,
        array $issues,
    ): void {
        foreach ($issues as $issue) {
            TrainingPathCourseApproval::query()->updateOrCreate([
                'importazione_id' => $importazione->getKey(),
                'user_id' => $user->getKey(),
                'training_path_id' => $trainingPath->getKey(),
                'course_id' => $issue['course_id'],
            ], [
                'status' => TrainingPathCourseApproval::STATUS_PENDING,
                'reasons' => $issue['reasons'],
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);
        }
    }

    public function decideImportEnrollment(
        Importazione $importazione,
        User $user,
        TrainingPath $trainingPath,
        User $reviewer,
        bool $approved,
    ): void {
        DB::transaction(function () use ($importazione, $user, $trainingPath, $reviewer, $approved): void {
            $pendingApprovals = TrainingPathCourseApproval::query()
                ->whereBelongsTo($importazione)
                ->whereBelongsTo($user)
                ->whereBelongsTo($trainingPath)
                ->where('status', TrainingPathCourseApproval::STATUS_PENDING)
                ->lockForUpdate()
                ->get();

            if ($pendingApprovals->isEmpty()) {
                return;
            }

            TrainingPathCourseApproval::query()
                ->whereKey($pendingApprovals->modelKeys())
                ->update([
                    'status' => $approved
                        ? TrainingPathCourseApproval::STATUS_APPROVED
                        : TrainingPathCourseApproval::STATUS_REJECTED,
                    'reviewed_by' => $reviewer->getKey(),
                    'reviewed_at' => now(),
                ]);

            if ($approved) {
                $this->persistEnrollment($user, $trainingPath, true);
            }

            $this->finishImportWhenReviewed($importazione);
        });
    }

    public function approveAllPending(Importazione $importazione, User $reviewer): void
    {
        DB::transaction(function () use ($importazione, $reviewer): void {
            $groups = TrainingPathCourseApproval::query()
                ->whereBelongsTo($importazione)
                ->where('status', TrainingPathCourseApproval::STATUS_PENDING)
                ->get(['user_id', 'training_path_id'])
                ->unique(fn (TrainingPathCourseApproval $approval): string => $approval->user_id.'-'.$approval->training_path_id);

            foreach ($groups as $group) {
                $this->decideImportEnrollment(
                    $importazione,
                    User::query()->findOrFail($group->user_id),
                    TrainingPath::query()->findOrFail($group->training_path_id),
                    $reviewer,
                    true,
                );
            }
        });
    }

    private function persistEnrollment(
        User $user,
        TrainingPath $trainingPath,
        bool $allowIneligibleCourses,
    ): TrainingPathEnrollment {
        if ($trainingPath->status !== 'published') {
            throw ValidationException::withMessages([
                'training_path' => __('Non puoi iscrivere utenti a un percorso non pubblicato.'),
            ]);
        }

        $enrollment = TrainingPathEnrollment::withTrashed()
            ->whereBelongsTo($trainingPath, 'trainingPath')
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->first();

        if ($enrollment?->trashed()) {
            $enrollment->restore();
            $enrollment->refresh();
        } elseif ($enrollment === null) {
            $enrollment = TrainingPathEnrollment::enroll($user, $trainingPath, $allowIneligibleCourses);
        }

        $this->trainingPathEnrollmentSyncService->syncEnrollment($enrollment);

        return $enrollment;
    }

    private function finishImportWhenReviewed(Importazione $importazione): void
    {
        $hasPendingApprovals = TrainingPathCourseApproval::query()
            ->whereBelongsTo($importazione)
            ->where('status', TrainingPathCourseApproval::STATUS_PENDING)
            ->exists();

        if (! $hasPendingApprovals) {
            $importazione->update([
                'status' => Importazione::STATUS_FINISHED,
                'finished_at' => now(),
                'error_message' => null,
            ]);
        }
    }
}
