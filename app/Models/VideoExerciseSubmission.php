<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoExerciseSubmission extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'video_exercise_id',
        'course_user_id',
        'user_id',
        'status',
        'elapsed_seconds',
        'downloaded_material_ids',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'video_exercise_id' => 'integer',
            'course_user_id' => 'integer',
            'user_id' => 'integer',
            'elapsed_seconds' => 'integer',
            'downloaded_material_ids' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(VideoExercise::class, 'video_exercise_id');
    }

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'course_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(VideoExerciseAnswer::class, 'video_exercise_submission_id');
    }
}
