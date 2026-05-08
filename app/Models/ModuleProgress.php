<?php

namespace App\Models;

use Database\Factories\ModuleProgressFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ModuleProgress extends Model
{
    /** @use HasFactory<ModuleProgressFactory> */
    use HasFactory;

    public const STATUS_LOCKED = 'locked';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'module_user';

    protected $fillable = [
        'course_user_id',
        'module_id',
        'status',
        'started_at',
        'completed_at',
        'last_accessed_at',
        'time_spent_seconds',
        'video_current_second',
        'video_max_second',
        'quiz_attempts',
        'quiz_score',
        'quiz_total_score',
        'passed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'passed_at' => 'datetime',
            'time_spent_seconds' => 'integer',
            'video_current_second' => 'integer',
            'video_max_second' => 'integer',
            'quiz_attempts' => 'integer',
            'quiz_score' => 'integer',
            'quiz_total_score' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ModuleProgress $progress): void {
            if ($progress->course_user_id === null || $progress->module_id === null) {
                return;
            }

            $courseId = CourseEnrollment::query()
                ->whereKey($progress->course_user_id)
                ->value('course_id');

            $belongsToCourse = Module::query()
                ->whereKey($progress->module_id)
                ->where('belongsTo', (string) $courseId)
                ->exists();

            if (! $belongsToCourse) {
                throw new DomainException('The module progress must belong to the enrollment course.');
            }
        });
    }

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'course_user_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function start(): void
    {
        $this->assertTrackableCurrentModule();

        if ($this->status === self::STATUS_LOCKED) {
            throw new DomainException('Locked modules cannot be started.');
        }

        $this->forceFill([
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => $this->started_at ?? now(),
            'last_accessed_at' => now(),
        ])->save();

        $this->courseEnrollment()->firstOrFail()->markAsInProgress();
    }

    public function recordVideoProgress(int $currentSecond, int $additionalTimeSpentSeconds = 0): void
    {
        $this->loadMissing(['module', 'courseEnrollment']);

        if (! $this->module->isVideo()) {
            throw new DomainException('Video progress can only be recorded for video modules.');
        }

        // Se il video è già completato, aggiorna solo tracking senza modificare status
        if ($this->status === self::STATUS_COMPLETED) {
            $this->forceFill([
                'last_accessed_at' => now(),
                'time_spent_seconds' => max(0, $this->time_spent_seconds + $additionalTimeSpentSeconds),
                'video_current_second' => max(0, $currentSecond),
            ])->save();
            return;
        }

        // Per video non completati, verifica che sia il modulo corrente
        $this->assertTrackableCurrentModule();

        if ($this->status === self::STATUS_AVAILABLE) {
            $this->start();
            $this->refresh();
        }

        $this->forceFill([
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => $this->started_at ?? now(),
            'last_accessed_at' => now(),
            'time_spent_seconds' => max(0, $this->time_spent_seconds + $additionalTimeSpentSeconds),
            'video_current_second' => max(0, $currentSecond),
            'video_max_second' => max($this->video_max_second ?? 0, $currentSecond),
        ])->save();

        $this->courseEnrollment->markAsInProgress();
    }

    public function markCompleted(): void
    {
        $this->loadMissing(['module', 'courseEnrollment']);
        $this->assertTrackableCurrentModule();

        if ($this->module->isQuiz()) {
            $this->assertQuizPassed();
        }

        DB::transaction(function (): void {
            $this->forceFill([
                'status' => self::STATUS_COMPLETED,
                'started_at' => $this->started_at ?? now(),
                'completed_at' => now(),
                'last_accessed_at' => now(),
                'passed_at' => $this->module->isQuiz() ? ($this->passed_at ?? now()) : $this->passed_at,
            ])->save();

            $this->courseEnrollment->advanceAfterModuleCompletion($this);
        });
    }

    public function recordQuizAttempt(int $score, int $totalScore): void
    {
        $this->loadMissing(['module', 'courseEnrollment']);

        if (! $this->module->isQuiz()) {
            throw new DomainException('Quiz attempts can only be recorded for quiz modules.');
        }

        $this->assertTrackableCurrentModule();

        $passingScore = $this->module->passing_score;

        if ($passingScore === null) {
            throw new DomainException('Quiz modules require a passing score before tracking attempts.');
        }

        DB::transaction(function () use ($score, $totalScore, $passingScore): void {
            $this->forceFill([
                'status' => $score >= $passingScore ? self::STATUS_COMPLETED : self::STATUS_FAILED,
                'started_at' => $this->started_at ?? now(),
                'completed_at' => $score >= $passingScore ? now() : null,
                'last_accessed_at' => now(),
                'quiz_attempts' => $this->quiz_attempts + 1,
                'quiz_score' => $score,
                'quiz_total_score' => $totalScore,
                'passed_at' => $score >= $passingScore ? now() : null,
            ])->save();

            $this->courseEnrollment->markAsInProgress();

            if ($score >= $passingScore) {
                $this->courseEnrollment->advanceAfterModuleCompletion($this);

                return;
            }

            $this->courseEnrollment->syncProgressState();
        });
    }

    protected function assertTrackableCurrentModule(): void
    {
        $this->loadMissing('courseEnrollment');

        if ((int) $this->courseEnrollment->current_module_id !== (int) $this->module_id) {
            throw new DomainException('Only the current module can be tracked.');
        }
    }

    protected function assertQuizPassed(): void
    {
        $passingScore = $this->module->passing_score;

        if ($passingScore === null || ($this->quiz_score ?? 0) < $passingScore) {
            throw new DomainException('The quiz must be passed before it can be completed.');
        }
    }
}
