<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScormPackage extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'course_id',
        'module_id',
        'title',
        'description',
        'version',
        'identifier',
        'entry_point',
        'file_path',
        'extracted_path',
        'manifest_data',
        'sco_data',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'manifest_data' => 'array',
            'sco_data' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function tracking(): HasMany
    {
        return $this->hasMany(ScormTracking::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ScormSession::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
