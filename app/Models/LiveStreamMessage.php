<?php

namespace App\Models;

use Database\Factories\LiveStreamMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamMessage extends Model
{
    /** @use HasFactory<LiveStreamMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'live_stream_session_id',
        'user_id',
        'app_role',
        'body',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'live_stream_session_id' => 'integer',
            'user_id' => 'integer',
            'sent_at' => 'datetime',
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
}
