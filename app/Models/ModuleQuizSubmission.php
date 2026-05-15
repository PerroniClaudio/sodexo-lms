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

    // Stati per upload di quiz
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_FAILED = 'failed';

    // Stati per quiz online
    public const STATUS_STARTED = 'started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ABANDONED = 'abandoned';

    // Source types
    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_ONLINE = 'online';

    protected $fillable = [
        'module_id',
        'source_type',
        'user_id',
        'course_enrollment_id',
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
        'started_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_payload' => 'array',
            'processed_at' => 'datetime',
            'finalized_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
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

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'course_enrollment_id');
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
            self::STATUS_STARTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_SUBMITTED,
            self::STATUS_ABANDONED,
        ]);
    }

    public function isOnline(): bool
    {
        return $this->source_type === self::SOURCE_ONLINE;
    }

    public function isUpload(): bool
    {
        return $this->source_type === self::SOURCE_UPLOAD;
    }
}
