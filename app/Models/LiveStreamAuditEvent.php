<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamAuditEvent extends Model
{
    public const TYPE_PARTICIPANT_JOINED = 'participant_joined';

    public const TYPE_PARTICIPANT_DISCONNECTED = 'participant_disconnected';

    public const TYPE_HAND_RAISE_REQUESTED = 'hand_raise_requested';

    protected $fillable = [
        'live_stream_session_id',
        'module_id',
        'user_id',
        'live_stream_participant_id',
        'live_stream_hand_raise_id',
        'event_type',
        'app_role',
        'occurred_at',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'live_stream_session_id' => 'integer',
            'module_id' => 'integer',
            'user_id' => 'integer',
            'live_stream_participant_id' => 'integer',
            'live_stream_hand_raise_id' => 'integer',
            'occurred_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveStreamSession::class, 'live_stream_session_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(LiveStreamParticipant::class, 'live_stream_participant_id');
    }

    public function handRaise(): BelongsTo
    {
        return $this->belongsTo(LiveStreamHandRaise::class, 'live_stream_hand_raise_id');
    }
}
