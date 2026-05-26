<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamSessionLog extends Model
{
    protected $fillable = [
        'live_stream_session_id',
        'module_id',
        'teacher_user_id',
        'source_role',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'twilio_room_name',
        'participant_identity',
        'event_count',
        'stats_snapshot_count',
        'max_participant_count',
        'started_at',
        'ended_at',
        'exported_at',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'live_stream_session_id' => 'integer',
            'module_id' => 'integer',
            'teacher_user_id' => 'integer',
            'size_bytes' => 'integer',
            'event_count' => 'integer',
            'stats_snapshot_count' => 'integer',
            'max_participant_count' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'exported_at' => 'datetime',
            'summary' => 'array',
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    protected function storageReference(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->disk}:{$this->path}");
    }
}
