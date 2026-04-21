<?php

namespace App\Models;

use Database\Factories\LiveStreamPollResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamPollResponse extends Model
{
    /** @use HasFactory<LiveStreamPollResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'live_stream_poll_id',
        'user_id',
        'answer_index',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'live_stream_poll_id' => 'integer',
            'user_id' => 'integer',
            'answer_index' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(LiveStreamPoll::class, 'live_stream_poll_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
