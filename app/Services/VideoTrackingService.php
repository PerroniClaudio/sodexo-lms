<?php

namespace App\Services;

use App\Models\ModuleProgress;
use App\Models\Video;
use App\Models\VideoExerciseSubmission;
use App\Models\VideoTrackingEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

class VideoTrackingService
{
    public const COMPLETION_THRESHOLD_PERCENT = 95;

    public const SEEK_GRACE_SECONDS = 3;

    public const MAX_DELTA_WATCHED_SECONDS = 75;

    public const TRANSACTION_ATTEMPTS = 5;

    public function state(ModuleProgress $progress): array
    {
        $progress->loadMissing(['module.video']);

        $video = $progress->module->video;

        if (! $progress->module->isVideo() || ! $video instanceof Video) {
            throw new DomainException('Video tracking requires a valid video module.');
        }

        $durationSeconds = $video->duration_seconds;
        $resumeSecond = $this->normalizeSecond($progress->video_current_second ?? 0, $durationSeconds);
        $maxAllowedSecond = $this->normalizeSecond(
            max($progress->video_max_second ?? 0, $resumeSecond),
            $durationSeconds,
        );

        return [
            'resume_second' => $resumeSecond,
            'max_allowed_second' => $maxAllowedSecond,
            'duration_seconds' => $durationSeconds,
            'completion_threshold_percent' => self::COMPLETION_THRESHOLD_PERCENT,
            'is_completed' => $progress->status === ModuleProgress::STATUS_COMPLETED,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function recordEvent(ModuleProgress $progress, array $payload): array
    {
        $progress->loadMissing(['courseEnrollment', 'courseEnrollment.user', 'module.video']);

        $video = $progress->module->video;

        if (! $progress->module->isVideo() || ! $video instanceof Video) {
            throw new DomainException('Video tracking requires a valid video module.');
        }

        $durationSeconds = $video->duration_seconds;
        $currentSecond = $this->normalizeSecond($progress->video_current_second ?? 0, $durationSeconds);
        $maxAllowedSecond = $this->normalizeSecond($progress->video_max_second ?? 0, $durationSeconds);
        $positionSecond = $this->resolvePositionSecond($payload, $durationSeconds, $currentSecond);
        $deltaWatchedSeconds = min(
            max(0, (int) ($payload['delta_watched_seconds'] ?? 0)),
            self::MAX_DELTA_WATCHED_SECONDS,
        );
        $movementWindowSeconds = min(
            max(0, $positionSecond - $currentSecond),
            self::MAX_DELTA_WATCHED_SECONDS,
        );
        $wasBlocked = false;

        if (($payload['event_type'] ?? null) === VideoTrackingEvent::TYPE_SEEK) {
            $targetSecond = $this->normalizeSecond(
                (int) ($payload['to_second'] ?? $positionSecond),
                $durationSeconds,
            );

            if ($targetSecond > ($maxAllowedSecond + self::SEEK_GRACE_SECONDS)) {
                $wasBlocked = true;
                $currentSecond = $maxAllowedSecond;
                $deltaWatchedSeconds = 0;
            } else {
                $currentSecond = $targetSecond;
                $deltaWatchedSeconds = 0;
            }
        } else {
            $deltaWatchedSeconds = max($deltaWatchedSeconds, $movementWindowSeconds);
            $allowedForwardSecond = $maxAllowedSecond + max(1, $deltaWatchedSeconds) + self::SEEK_GRACE_SECONDS;

            if ($positionSecond > $allowedForwardSecond) {
                $wasBlocked = true;
                $positionSecond = $maxAllowedSecond;
                $deltaWatchedSeconds = 0;
            }

            $currentSecond = $positionSecond;
            $maxAllowedSecond = max($maxAllowedSecond, $positionSecond);
        }

        $duplicate = false;

        DB::transaction(function () use (
            $progress,
            $video,
            $payload,
            $currentSecond,
            $maxAllowedSecond,
            $deltaWatchedSeconds,
            $wasBlocked,
            &$duplicate
        ): void {
            $lockedProgress = ModuleProgress::query()
                ->with(['module', 'courseEnrollment'])
                ->lockForUpdate()
                ->findOrFail($progress->getKey());

            $existingEvent = VideoTrackingEvent::query()
                ->where('event_uuid', $payload['event_uuid'])
                ->first();

            if ($existingEvent !== null) {
                $duplicate = true;
                $progress->setRawAttributes($lockedProgress->getAttributes(), true);

                return;
            }

            $lockedProgress->syncVideoTrackingState($currentSecond, $maxAllowedSecond, $deltaWatchedSeconds);

            $lockedProgress->refresh();

            if (
                $lockedProgress->status !== ModuleProgress::STATUS_COMPLETED
                && ! $wasBlocked
                && $this->shouldMarkCompleted($lockedProgress, $video, $payload)
            ) {
                $lockedProgress->markCompleted();
                $lockedProgress->refresh();
            }

            VideoTrackingEvent::query()->create([
                'module_progress_id' => $lockedProgress->getKey(),
                'course_user_id' => $lockedProgress->course_user_id,
                'module_id' => $lockedProgress->module_id,
                'video_id' => $video->getKey(),
                'user_id' => $lockedProgress->courseEnrollment->user_id,
                'session_uuid' => $payload['session_uuid'],
                'event_uuid' => $payload['event_uuid'],
                'event_type' => $payload['event_type'],
                'position_second' => $payload['position_second'] ?? null,
                'max_second_client' => $payload['max_second_client'] ?? null,
                'delta_watched_seconds' => $deltaWatchedSeconds,
                'from_second' => $payload['from_second'] ?? null,
                'to_second' => $payload['to_second'] ?? null,
                'player_ended' => (bool) ($payload['player_ended'] ?? false),
                'was_blocked' => $wasBlocked,
                'occurred_at' => $payload['occurred_at'],
                'client_payload' => $payload['client_payload'] ?? null,
            ]);

            $progress->setRawAttributes($lockedProgress->getAttributes(), true);
            $progress->setRelations($lockedProgress->getRelations());
        }, self::TRANSACTION_ATTEMPTS);

        $progress->refresh();
        $state = $this->state($progress);

        return [
            ...$state,
            'accepted_second' => $state['resume_second'],
            'rewind_to_second' => (! $duplicate && $wasBlocked) ? $state['max_allowed_second'] : null,
            'was_blocked' => ! $duplicate && $wasBlocked,
            'duplicate' => $duplicate,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldMarkCompleted(ModuleProgress $progress, Video $video, array $payload): bool
    {
        $durationSeconds = $video->duration_seconds;
        $playerEnded = (bool) ($payload['player_ended'] ?? false)
            || ($payload['event_type'] ?? null) === VideoTrackingEvent::TYPE_ENDED;

        if (! $playerEnded || $durationSeconds === null || $durationSeconds <= 0) {
            return false;
        }

        if ((($progress->video_max_second ?? 0) / $durationSeconds) < (self::COMPLETION_THRESHOLD_PERCENT / 100)) {
            return false;
        }

        $exerciseIds = $progress->module->videoExercises()->pluck('id');

        if ($exerciseIds->isEmpty()) {
            return true;
        }

        $completedExercises = VideoExerciseSubmission::query()
            ->where('course_user_id', $progress->course_user_id)
            ->whereIn('video_exercise_id', $exerciseIds)
            ->where('status', VideoExerciseSubmission::STATUS_COMPLETED)
            ->distinct('video_exercise_id')
            ->count('video_exercise_id');

        return $completedExercises === $exerciseIds->count();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePositionSecond(array $payload, ?int $durationSeconds, int $fallback): int
    {
        $positionSecond = $payload['position_second']
            ?? $payload['to_second']
            ?? $payload['from_second']
            ?? $fallback;

        return $this->normalizeSecond((int) $positionSecond, $durationSeconds);
    }

    private function normalizeSecond(int $second, ?int $durationSeconds): int
    {
        $second = max(0, $second);

        if ($durationSeconds === null || $durationSeconds <= 0) {
            return $second;
        }

        return min($second, $durationSeconds);
    }
}
