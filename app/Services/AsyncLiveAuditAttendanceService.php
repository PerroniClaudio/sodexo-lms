<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\LiveStreamAuditEvent;
use App\Models\Module;
use App\Models\ModuleProgress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AsyncLiveAuditAttendanceService
{
    /**
     * @return array<string, int>
     */
    public function confirmAttendance(
        Module $module,
        CarbonImmutable $effectiveStartAt,
        CarbonImmutable $effectiveEndAt,
        int $minimumAttendancePercentage,
    ): array {
        $durationSeconds = $effectiveEndAt->getTimestamp() - $effectiveStartAt->getTimestamp();
        $thresholdSeconds = (int) ceil($durationSeconds * ($minimumAttendancePercentage / 100));
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
        $attendanceSecondsByUserId = $this->attendanceSecondsByUserId($module, $effectiveStartAt, $effectiveEndAt);

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
     * @return array<int, int>
     */
    public function attendanceSecondsByUserId(
        Module $module,
        CarbonImmutable $effectiveStartAt,
        CarbonImmutable $effectiveEndAt,
    ): array {
        return DB::table('live_stream_audit_events')
            ->where('module_id', $module->getKey())
            ->whereIn('event_type', [
                LiveStreamAuditEvent::TYPE_PARTICIPANT_JOINED,
                LiveStreamAuditEvent::TYPE_PARTICIPANT_DISCONNECTED,
            ])
            ->orderBy('user_id')
            ->orderBy('occurred_at')
            ->get(['user_id', 'event_type', 'occurred_at'])
            ->groupBy('user_id')
            ->map(fn (Collection $events): int => $this->attendanceSeconds($events, $effectiveStartAt, $effectiveEndAt))
            ->all();
    }

    /**
     * @param  Collection<int, object{event_type: string, occurred_at: string}>  $events
     */
    private function attendanceSeconds(Collection $events, CarbonImmutable $effectiveStartAt, CarbonImmutable $effectiveEndAt): int
    {
        $joinedAt = null;
        $totalSeconds = 0;

        foreach ($events as $event) {
            if ($event->event_type === LiveStreamAuditEvent::TYPE_PARTICIPANT_JOINED) {
                $joinedAt = CarbonImmutable::parse($event->occurred_at);

                continue;
            }

            if ($event->event_type !== LiveStreamAuditEvent::TYPE_PARTICIPANT_DISCONNECTED || $joinedAt === null) {
                continue;
            }

            $leftAt = CarbonImmutable::parse($event->occurred_at);
            $start = $joinedAt->greaterThan($effectiveStartAt) ? $joinedAt : $effectiveStartAt;
            $end = $leftAt->lessThan($effectiveEndAt) ? $leftAt : $effectiveEndAt;
            $totalSeconds += max(0, $end->getTimestamp() - $start->getTimestamp());
            $joinedAt = null;
        }

        return $totalSeconds;
    }
}
