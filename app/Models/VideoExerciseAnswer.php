<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoExerciseAnswer extends Model
{
    protected $fillable = [
        'video_exercise_submission_id',
        'video_exercise_question_id',
        'answer_text',
    ];

    protected function casts(): array
    {
        return [
            'video_exercise_submission_id' => 'integer',
            'video_exercise_question_id' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(VideoExerciseSubmission::class, 'video_exercise_submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(VideoExerciseQuestion::class, 'video_exercise_question_id');
    }
}
