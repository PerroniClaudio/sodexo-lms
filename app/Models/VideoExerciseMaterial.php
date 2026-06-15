<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoExerciseMaterial extends Model
{
    public const TYPE_FILE = 'file';

    public const TYPE_VIDEO = 'video';

    public const TYPE_TEXT = 'text';

    protected $fillable = [
        'video_exercise_id',
        'uploaded_by',
        'type',
        'title',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'youtube_url',
        'content_html',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'video_exercise_id' => 'integer',
            'uploaded_by' => 'integer',
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(VideoExercise::class, 'video_exercise_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function youtubeEmbedUrl(): ?string
    {
        return (new VideoExercise(['youtube_url' => $this->youtube_url]))->youtubeEmbedUrl();
    }
}
