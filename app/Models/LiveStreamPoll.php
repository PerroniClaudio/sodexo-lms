<?php

namespace App\Models;

use Database\Factories\LiveStreamPollFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveStreamPoll extends Model
{
    /** @use HasFactory<LiveStreamPollFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'live_stream_session_id',
        'user_id',
        'question',
        'options',
        'status',
        'published_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'live_stream_session_id' => 'integer',
            'user_id' => 'integer',
            'options' => 'array',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function responses(): HasMany
    {
        return $this->hasMany(LiveStreamPollResponse::class, 'live_stream_poll_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
