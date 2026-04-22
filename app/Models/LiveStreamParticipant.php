<?php

namespace App\Models;

use Database\Factories\LiveStreamParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamParticipant extends Model
{
    /** @use HasFactory<LiveStreamParticipantFactory> */
    use HasFactory;

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_USER = 'user';

    public const ROLE_TUTOR = 'tutor';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'live_stream_session_id',
        'user_id',
        'app_role',
        'twilio_identity',
        'twilio_participant_sid',
        'is_hidden',
        'audio_enabled',
        'video_enabled',
        'joined_at',
        'last_seen_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'live_stream_session_id' => 'integer',
            'user_id' => 'integer',
            'is_hidden' => 'boolean',
            'audio_enabled' => 'boolean',
            'video_enabled' => 'boolean',
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveStreamSession::class, 'live_stream_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVisibleStudent(): bool
    {
        return $this->app_role === self::ROLE_USER && ! $this->is_hidden && $this->left_at === null;
    }
}
