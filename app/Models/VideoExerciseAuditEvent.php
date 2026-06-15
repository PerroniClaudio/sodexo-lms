<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoExerciseAuditEvent extends Model
{
    public const TYPE_STARTED = 'STARTED';

    public const TYPE_REOPENED = 'REOPENED';

    public const TYPE_SAVED = 'SAVED';

    public const TYPE_SUBMITTED = 'SUBMITTED';

    protected $fillable = [
        'video_exercise_id',
        'video_exercise_submission_id',
        'course_user_id',
        'user_id',
        'event_type',
        'completion_percentage',
        'elapsed_seconds',
        'started_at',
        'completed_at',
        'occurred_at',
        'updated_at_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'video_exercise_id' => 'integer',
            'video_exercise_submission_id' => 'integer',
            'course_user_id' => 'integer',
            'user_id' => 'integer',
            'completion_percentage' => 'integer',
            'elapsed_seconds' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'occurred_at' => 'datetime',
            'updated_at_snapshot' => 'datetime',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(VideoExercise::class, 'video_exercise_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(VideoExerciseSubmission::class, 'video_exercise_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
