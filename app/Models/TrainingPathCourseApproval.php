<?php

namespace App\Models;

use Database\Factories\TrainingPathCourseApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingPathCourseApproval extends Model
{
    /** @use HasFactory<TrainingPathCourseApprovalFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'importazione_id',
        'user_id',
        'training_path_id',
        'course_id',
        'status',
        'reasons',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function importazione(): BelongsTo
    {
        return $this->belongsTo(Importazione::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function trainingPath(): BelongsTo
    {
        return $this->belongsTo(TrainingPath::class)->withTrashed();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
