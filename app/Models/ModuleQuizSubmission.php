<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ModuleQuizSubmission extends Model
{
    use HasFactory;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'module_id',
        'user_id',
        'uploaded_by',
        'finalized_by',
        'disk',
        'path',
        'status',
        'score',
        'total_score',
        'provider',
        'provider_payload',
        'error_message',
        'processed_at',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_payload' => 'array',
            'processed_at' => 'datetime',
            'finalized_at' => 'datetime',
            'score' => 'integer',
            'total_score' => 'integer',
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

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ModuleQuizSubmissionAnswer::class);
    }

    /**
     * @return Collection<int, string>
     */
    public static function availableStatuses(): Collection
    {
        return collect([
            self::STATUS_UPLOADED,
            self::STATUS_PROCESSING,
            self::STATUS_NEEDS_REVIEW,
            self::STATUS_FINALIZED,
            self::STATUS_FAILED,
        ]);
    }
}
