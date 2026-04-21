<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamAttendanceMinute extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_stream_session_id',
        'module_id',
        'user_id',
        'minute_at',
        'first_seen_at',
        'last_seen_at',
        'heartbeat_count',
    ];

    protected function casts(): array
    {
        return [
            'live_stream_session_id' => 'integer',
            'module_id' => 'integer',
            'user_id' => 'integer',
            'minute_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'heartbeat_count' => 'integer',
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
}
