<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\LiveStreamAttendanceMinute;
use App\Models\Module;
use App\Models\ModuleProgress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LiveModuleAttendanceService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function buildReport(
        Module $module,
        ?CarbonImmutable $effectiveStartAt = null,
        ?CarbonImmutable $effectiveEndAt = null,
        ?int $minimumAttendancePercentage = null,
    ): Collection {
        $enrollments = CourseEnrollment::query()
            ->where('course_id', (int) $module->belongsTo)
            ->whereNull('deleted_at')
            ->with([
                'user',
                'moduleProgresses' => fn ($query) => $query->where('module_id', $module->getKey()),
            ])
            ->get();

        $attendanceMinutesByUser = LiveStreamAttendanceMinute::query()
            ->where('module_id', $module->getKey())
            ->orderBy('minute_at')
            ->get(['user_id', 'minute_at'])
            ->groupBy('user_id');

        return $enrollments
            ->map(function (CourseEnrollment $enrollment) use ($attendanceMinutesByUser, $effectiveStartAt, $effectiveEndAt, $minimumAttendancePercentage, $module): array {
                /** @var Collection<int, LiveStreamAttendanceMinute> $attendanceMinutes */
                $attendanceMinutes = $attendanceMinutesByUser->get($enrollment->user_id, collect());
                $attendanceSeconds = $this->attendanceSeconds($attendanceMinutes, $effectiveStartAt, $effectiveEndAt);
                $attendancePercentage = $this->attendancePercentage($attendanceSeconds, $effectiveStartAt, $effectiveEndAt);
                $progress = $enrollment->moduleProgresses->first();
                $qualifies = $attendancePercentage !== null
                    && $minimumAttendancePercentage !== null
                    && $attendancePercentage >= $minimumAttendancePercentage;

                return [
                    'user' => $enrollment->user,
                    'enrollment' => $enrollment,
                    'progress' => $progress,
                    'attendance_seconds' => $attendanceSeconds,
                    'attendance_percentage' => $attendancePercentage,
                    'qualifies' => $qualifies,
                    'is_current_module' => (int) $enrollment->current_module_id === (int) $module->getKey(),
                    'can_be_confirmed' => $qualifies && $progress instanceof ModuleProgress
                        && $progress->status !== ModuleProgress::STATUS_COMPLETED
                        && (int) $enrollment->current_module_id === (int) $module->getKey(),
                ];
            })
            ->filter(fn (array $row): bool => $row['attendance_seconds'] > 0)
            ->sortBy(fn (array $row): string => mb_strtolower(trim(sprintf(
                '%s %s',
                $row['user']?->surname ?? '',
                $row['user']?->name ?? '',
            ))))
            ->values();
    }

    /**
     * @return array<string, int>
     */
    public function confirmAttendance(
        Module $module,
        CarbonImmutable $effectiveStartAt,
        CarbonImmutable $effectiveEndAt,
        int $minimumAttendancePercentage,
    ): array {
        $report = $this->buildReport($module, $effectiveStartAt, $effectiveEndAt, $minimumAttendancePercentage);

        $stats = [
            'participants' => $report->count(),
            'qualified' => 0,
            'confirmed' => 0,
            'already_completed' => 0,
            'skipped_not_current' => 0,
        ];

        DB::transaction(function () use ($report, &$stats): void {
            foreach ($report as $row) {
                if (! $row['qualifies']) {
                    continue;
                }

                $stats['qualified']++;

                /** @var ModuleProgress|null $progress */
                $progress = $row['progress'];

                if (! $progress instanceof ModuleProgress) {
                    continue;
                }

                if ($progress->status === ModuleProgress::STATUS_COMPLETED) {
                    $stats['already_completed']++;

                    continue;
                }

                if (! $row['is_current_module']) {
                    $stats['skipped_not_current']++;

                    continue;
                }

                $progress->markCompleted();
                $stats['confirmed']++;
            }
        });

        return $stats;
    }

    /**
     * @param  Collection<int, LiveStreamAttendanceMinute>  $attendanceMinutes
     */
    private function attendanceSeconds(
        Collection $attendanceMinutes,
        ?CarbonImmutable $effectiveStartAt,
        ?CarbonImmutable $effectiveEndAt,
    ): int {
        return $attendanceMinutes->sum(
            fn (LiveStreamAttendanceMinute $attendanceMinute): int => $this->minuteOverlapSeconds(
                $attendanceMinute,
                $effectiveStartAt,
                $effectiveEndAt,
            )
        );
    }

    private function attendancePercentage(
        int $attendanceSeconds,
        ?CarbonImmutable $effectiveStartAt,
        ?CarbonImmutable $effectiveEndAt,
    ): ?int {
        if ($effectiveStartAt === null || $effectiveEndAt === null) {
            return null;
        }

        $liveDurationSeconds = $effectiveEndAt->getTimestamp() - $effectiveStartAt->getTimestamp();

        if ($liveDurationSeconds <= 0) {
            return null;
        }

        return (int) round(min(100, ($attendanceSeconds / $liveDurationSeconds) * 100));
    }

    private function minuteOverlapSeconds(
        LiveStreamAttendanceMinute $attendanceMinute,
        ?CarbonImmutable $effectiveStartAt,
        ?CarbonImmutable $effectiveEndAt,
    ): int {
        $minuteStart = CarbonImmutable::instance($attendanceMinute->minute_at);
        $minuteEnd = $minuteStart->addMinute();

        if ($effectiveStartAt === null || $effectiveEndAt === null) {
            return 60;
        }

        $overlapStart = $minuteStart->greaterThan($effectiveStartAt) ? $minuteStart : $effectiveStartAt;
        $overlapEnd = $minuteEnd->lessThan($effectiveEndAt) ? $minuteEnd : $effectiveEndAt;

        return max(0, $overlapEnd->getTimestamp() - $overlapStart->getTimestamp());
    }
}
