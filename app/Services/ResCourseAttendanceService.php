<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResCourseAttendanceService
{
    /**
     * @return array<string, int>
     */
    public function confirmAttendance(Module $module, int $minimumAttendancePercentage): array
    {
        $classWindows = $this->completedClassWindows($module);
        $thresholdSeconds = (int) ceil($classWindows->sum(
            fn (array $window): int => $window['end']->getTimestamp() - $window['start']->getTimestamp()
        ) * ($minimumAttendancePercentage / 100));

        $stats = [
            'participants' => 0,
            'qualified' => 0,
            'confirmed' => 0,
            'already_completed' => 0,
            'skipped_not_current' => 0,
        ];

        if ($thresholdSeconds <= 0) {
            return $stats;
        }

        $enrollments = CourseEnrollment::query()
            ->where('course_id', (int) $module->belongsTo)
            ->whereNull('deleted_at')
            ->with(['moduleProgresses' => fn ($query) => $query->where('module_id', $module->getKey())])
            ->get();

        $attendanceSecondsByUserId = $this->attendanceSecondsByUserId((int) $module->belongsTo, $classWindows);

        DB::transaction(function () use ($attendanceSecondsByUserId, $enrollments, $module, $thresholdSeconds, &$stats): void {
            foreach ($enrollments as $enrollment) {
                $attendanceSeconds = (int) ($attendanceSecondsByUserId[$enrollment->user_id] ?? 0);

                if ($attendanceSeconds <= 0) {
                    continue;
                }

                $stats['participants']++;

                if ($attendanceSeconds < $thresholdSeconds) {
                    continue;
                }

                $stats['qualified']++;

                /** @var ModuleProgress|null $progress */
                $progress = $enrollment->moduleProgresses->first();

                if (! $progress instanceof ModuleProgress) {
                    continue;
                }

                if ($progress->status === ModuleProgress::STATUS_COMPLETED) {
                    $stats['already_completed']++;

                    continue;
                }

                if ((int) $enrollment->current_module_id !== (int) $module->getKey()) {
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
     * @return Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function completedClassWindows(Module $module): Collection
    {
        return DB::table('course_class_schedules')
            ->join('course_classes', 'course_classes.id', '=', 'course_class_schedules.course_class_id')
            ->where('course_classes.module_id', $module->getKey())
            ->whereNull('course_classes.deleted_at')
            ->where('course_class_schedules.ends_at', '<=', now())
            ->orderBy('course_class_schedules.starts_at')
            ->get(['course_class_schedules.starts_at', 'course_class_schedules.ends_at'])
            ->map(fn ($schedule): array => [
                'start' => CarbonImmutable::parse($schedule->starts_at),
                'end' => CarbonImmutable::parse($schedule->ends_at),
            ]);
    }

    /**
     * @param  Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $classWindows
     * @return array<int, int>
     */
    private function attendanceSecondsByUserId(int $courseId, Collection $classWindows): array
    {
        return DB::table('course_attendance_records')
            ->where('course_id', $courseId)
            ->orderBy('user_id')
            ->orderBy('recorded_at')
            ->get(['user_id', 'type', 'recorded_at'])
            ->groupBy('user_id')
            ->map(fn (Collection $records): int => $this->attendanceSeconds($records, $classWindows))
            ->all();
    }

    /**
     * @param  Collection<int, object{type: string, recorded_at: string}>  $records
     * @param  Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $classWindows
     */
    private function attendanceSeconds(Collection $records, Collection $classWindows): int
    {
        $entryAt = null;
        $totalSeconds = 0;

        foreach ($records as $record) {
            if ($record->type === 'entry') {
                $entryAt = CarbonImmutable::parse($record->recorded_at);

                continue;
            }

            if ($record->type !== 'exit' || $entryAt === null) {
                continue;
            }

            $exitAt = CarbonImmutable::parse($record->recorded_at);

            if ($exitAt->greaterThan($entryAt)) {
                $totalSeconds += $this->overlapSeconds($entryAt, $exitAt, $classWindows);
            }

            $entryAt = null;
        }

        return $totalSeconds;
    }

    /**
     * @param  Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $classWindows
     */
    private function overlapSeconds(CarbonImmutable $entryAt, CarbonImmutable $exitAt, Collection $classWindows): int
    {
        return $classWindows->sum(function (array $window) use ($entryAt, $exitAt): int {
            $start = $entryAt->greaterThan($window['start']) ? $entryAt : $window['start'];
            $end = $exitAt->lessThan($window['end']) ? $exitAt : $window['end'];

            return max(0, $end->getTimestamp() - $start->getTimestamp());
        });
    }
}
