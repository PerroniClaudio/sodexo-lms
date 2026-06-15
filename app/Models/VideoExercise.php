<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VideoExercise extends Model
{
    protected $fillable = [
        'module_id',
        'title',
        'appears_at_seconds',
        'minimum_seconds',
        'support_text_html',
        'youtube_url',
        'self_evaluation_disk',
        'self_evaluation_path',
        'self_evaluation_original_name',
        'self_evaluation_mime_type',
        'self_evaluation_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'module_id' => 'integer',
            'appears_at_seconds' => 'integer',
            'minimum_seconds' => 'integer',
            'self_evaluation_size_bytes' => 'integer',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(VideoExerciseMaterial::class)->latest('uploaded_at');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(VideoExerciseQuestion::class)->orderBy('order')->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(VideoExerciseSubmission::class);
    }

    public function youtubeEmbedUrl(): ?string
    {
        if ($this->youtube_url === null || trim($this->youtube_url) === '') {
            return null;
        }

        $url = trim($this->youtube_url);
        $parts = parse_url($url);
        $host = Str::of($parts['host'] ?? '')->lower()->toString();
        $path = trim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);

        $videoId = match (true) {
            str_contains($host, 'youtu.be') => $path,
            str_contains($host, 'youtube.com') && isset($query['v']) => (string) $query['v'],
            str_contains($host, 'youtube.com') && str_starts_with($path, 'embed/') => Str::after($path, 'embed/')->toString(),
            str_contains($host, 'youtube.com') && str_starts_with($path, 'shorts/') => Str::after($path, 'shorts/')->toString(),
            default => null,
        };

        if ($videoId === null || $videoId === '') {
            return null;
        }

        return 'https://www.youtube.com/embed/'.rawurlencode($videoId);
    }
}
