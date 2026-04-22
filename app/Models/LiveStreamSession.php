<?php

namespace App\Models;

use Database\Factories\LiveStreamSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveStreamSession extends Model
{
    /** @use HasFactory<LiveStreamSessionFactory> */
    use HasFactory;

    public const STATUS_LIVE = 'live';

    public const STATUS_ENDED = 'ended';

    public const BROADCAST_STATUS_IDLE = 'idle';

    public const BROADCAST_STATUS_ACTIVE = 'active';

    public const BROADCAST_STATUS_ENDED = 'ended';

    protected $fillable = [
        'module_id',
        'teacher_user_id',
        'started_by_user_id',
        'regia_user_id',
        'twilio_room_sid',
        'twilio_room_name',
        'mux_playback_id',
        'mux_broadcast_status',
        'mux_broadcast_started_at',
        'mux_broadcast_ended_at',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'module_id' => 'integer',
            'teacher_user_id' => 'integer',
            'started_by_user_id' => 'integer',
            'regia_user_id' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'mux_broadcast_started_at' => 'datetime',
            'mux_broadcast_ended_at' => 'datetime',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function regia(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regia_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LiveStreamParticipant::class);
    }

    public function attendanceMinutes(): HasMany
    {
        return $this->hasMany(LiveStreamAttendanceMinute::class);
    }

    public function handRaises(): HasMany
    {
        return $this->hasMany(LiveStreamHandRaise::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LiveStreamMessage::class);
    }

    public function polls(): HasMany
    {
        return $this->hasMany(LiveStreamPoll::class);
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isBroadcastLive(): bool
    {
        return $this->mux_broadcast_status === self::BROADCAST_STATUS_ACTIVE;
    }
}
