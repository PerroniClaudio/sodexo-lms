<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoTrackingEvent extends Model
{
    public const TYPE_HEARTBEAT = 'heartbeat';

    public const TYPE_PLAY = 'play';

    public const TYPE_PAUSE = 'pause';

    public const TYPE_SEEK = 'seek';

    public const TYPE_ENDED = 'ended';

    public const TYPE_RESUME = 'resume';

    public const TYPE_LOADED = 'loaded';

    public const TYPES = [
        self::TYPE_HEARTBEAT,
        self::TYPE_PLAY,
        self::TYPE_PAUSE,
        self::TYPE_SEEK,
        self::TYPE_ENDED,
        self::TYPE_RESUME,
        self::TYPE_LOADED,
    ];

    protected $fillable = [
        'module_progress_id',
        'course_user_id',
        'module_id',
        'video_id',
        'user_id',
        'session_uuid',
        'event_uuid',
        'event_type',
        'position_second',
        'max_second_client',
        'delta_watched_seconds',
        'from_second',
        'to_second',
        'player_ended',
        'was_blocked',
        'occurred_at',
        'client_payload',
    ];

    protected function casts(): array
    {
        return [
            'position_second' => 'integer',
            'max_second_client' => 'integer',
            'delta_watched_seconds' => 'integer',
            'from_second' => 'integer',
            'to_second' => 'integer',
            'player_ended' => 'boolean',
            'was_blocked' => 'boolean',
            'occurred_at' => 'datetime',
            'client_payload' => 'array',
        ];
    }

    public function moduleProgress(): BelongsTo
    {
        return $this->belongsTo(ModuleProgress::class, 'module_progress_id');
    }

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'course_user_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
