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

    protected $fillable = [
        'module_id',
        'teacher_user_id',
        'twilio_room_sid',
        'twilio_room_name',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'module_id' => 'integer',
            'teacher_user_id' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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

    public function participants(): HasMany
    {
        return $this->hasMany(LiveStreamParticipant::class);
    }

    public function handRaises(): HasMany
    {
        return $this->hasMany(LiveStreamHandRaise::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LiveStreamMessage::class);
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }
}
