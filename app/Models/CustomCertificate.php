<?php

namespace App\Models;

use Database\Factories\CustomCertificateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomCertificate extends Model
{
    /** @use HasFactory<CustomCertificateFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_PARTICIPATION = 'participation';

    public const TYPE_COMPLETION = 'completion';

    protected $fillable = [
        'type',
        'name',
        'storage_disk',
        'template_path',
        'original_filename',
        'mime_type',
        'is_active',
        'course_ids',
        'job_sector_id',
        'replaced_by_id',
        'activated_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'course_ids' => 'array',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'archived_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public static function availableTypes(): array
    {
        return [
            self::TYPE_PARTICIPATION,
            self::TYPE_COMPLETION,
        ];
    }

    public static function availableTypeLabels(): array
    {
        return [
            self::TYPE_PARTICIPATION => __('Partecipazione'),
            self::TYPE_COMPLETION => __('Superamento'),
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * @param  array<int>|null  $courseIds
     */
    public function scopeForTarget(Builder $query, ?array $courseIds, ?int $jobSectorId): Builder
    {
        return $query
            ->where('course_ids', $courseIds === null ? null : json_encode(array_values($courseIds)))
            ->where('job_sector_id', $jobSectorId);
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function jobSector(): BelongsTo
    {
        return $this->belongsTo(JobSector::class);
    }

    public function isGeneric(): bool
    {
        return blank($this->course_ids) && $this->job_sector_id === null;
    }

    public function isForSector(int $jobSectorId): bool
    {
        return $this->job_sector_id === $jobSectorId;
    }

    public function supportsCourse(int $courseId): bool
    {
        if ($this->job_sector_id !== null) {
            return false;
        }

        if ($this->isGeneric()) {
            return true;
        }

        return in_array($courseId, $this->course_ids ?? [], true);
    }
}
