<?php

namespace App\Models;

use Database\Factories\LiveStreamDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamDocument extends Model
{
    /** @use HasFactory<LiveStreamDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'module_id',
        'user_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'module_id' => 'integer',
            'user_id' => 'integer',
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
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
