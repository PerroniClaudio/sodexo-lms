<?php

namespace App\Models;

use Database\Factories\LiveStreamHandRaiseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamHandRaise extends Model
{
    /** @use HasFactory<LiveStreamHandRaiseFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'live_stream_session_id',
        'user_id',
        'status',
        'requested_at',
        'approved_at',
        'resolved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'live_stream_session_id' => 'integer',
            'user_id' => 'integer',
            'approved_by' => 'integer',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
