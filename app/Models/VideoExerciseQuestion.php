<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoExerciseQuestion extends Model
{
    protected $fillable = [
        'video_exercise_id',
        'text',
        'minimum_characters',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'video_exercise_id' => 'integer',
            'minimum_characters' => 'integer',
            'order' => 'integer',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(VideoExercise::class, 'video_exercise_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(VideoExerciseAnswer::class);
    }
}
